<?php

namespace App\Services;

use App\Models\LocalRole;
use App\Models\SsoUser;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SsoService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = env('CENTRAL_SERVER_URL', 'https://iae-sso.virtualfri.id/');
        $this->apiKey  = env('CENTRAL_TEAM_API_KEY', 'KEY-MHS-01');
    }

    // =========================================================
    // 1. LOGIN M2M — Aplikasi Laravel minta token ke SSO
    //
    //  Response asli server Pa Eki:
    //  { "status":"success", "token_type":"m2m", "token":"eyJ...",
    //    "app": { "client_id":"KEY-MHS-01", "name":"...", "team":"TEAM-01" } }
    // =========================================================
    public function loginM2M(): string
    {
        $response = Http::post("{$this->baseUrl}/api/v1/auth/token", [
            'api_key' => $this->apiKey,
        ]);

        if (! $response->successful()) {
            Log::error('[SSO] M2M login gagal', ['response' => $response->body()]);
            throw new RuntimeException('SSO M2M login gagal: ' . $response->body());
        }

        // Key response dari Pa Eki adalah "token" (bukan "access_token")
        $token = $response->json('token')
            ?? throw new RuntimeException('Token tidak ditemukan di response SSO');

        Log::info('[SSO] M2M login berhasil', [
            'app_name' => $response->json('app.name'),
            'team'     => $response->json('app.team'),
        ]);

        return $token;
    }

    // =========================================================
    // 2. LOGIN USER — End-user (warga/mahasiswa) login via SSO
    //
    //  Response asli server Pa Eki:
    //  { "status":"success", "token_type":"user", "token":"eyJ...",
    //    "profile": { "name":"Ahmad Rizki Pratama", "nim":"2026000001",
    //                 "email":"warga01@ktp.iae.id" } }
    // =========================================================
    public function loginUser(string $email, string $password): array
    {
        $response = Http::post("{$this->baseUrl}/api/v1/auth/token", [
            'email'    => $email,
            'password' => $password,
        ]);

        if (! $response->successful()) {
            Log::warning('[SSO] User login gagal', ['email' => $email]);
            throw new RuntimeException('Email atau password salah');
        }

        $token = $response->json('token')
            ?? throw new RuntimeException('Token tidak ditemukan di response SSO');

        // Decode & verify JWT menggunakan JWKS
        $payload = $this->decodeAndVerify($token);

        // Petakan user ke role lokal di database kita
        $ssoUser = $this->mapToLocalRole($token, $payload);

        Log::info('[SSO] User login berhasil', [
            'email'      => $email,
            'local_role' => $ssoUser->localRole->name,
        ]);

        return [
            'token'    => $token,
            'sso_user' => $ssoUser,
            'payload'  => $payload,
        ];
    }

    // =========================================================
    // 3. VERIFY JWT — Validasi token dengan public key dari JWKS
    // =========================================================
    public function decodeAndVerify(string $token): array
{
    $jwks = $this->getPublicKeys();

    try {
        // Tambahkan leeway 300 detik (5 menit) untuk toleransi clock skew
        JWT::$leeway = 300;

        $keys    = JWK::parseKeySet($jwks);
        $decoded = JWT::decode($token, $keys);

        return (array) $decoded;
    } catch (\Exception $e) {
        Log::error('[SSO] JWT verification gagal', ['error' => $e->getMessage()]);
        throw new RuntimeException('Token tidak valid: ' . $e->getMessage());
    }
}

    // =========================================================
    // 4. MAP TO LOCAL ROLE — Inti penilaian Modul 1
    //    Menentukan role lokal berdasarkan data dari JWT
    //
    //  Struktur payload JWT dari Pa Eki:
    //
    //  M2M token:
    //  { "iss":"iae-central-mock", "sub":"KEY-MHS-01", "iat":..., "exp":...,
    //    "grant_type":"client_credentials", "token_type":"m2m",
    //    "app": { "client_id":"KEY-MHS-01", "name":"...", "team":"TEAM-01" } }
    //
    //  User token:
    //  { "iss":"iae-central-mock", "sub":"warga01@ktp.iae.id", "iat":..., "exp":...,
    //    "grant_type":"password", "token_type":"user",
    //    "profile": { "name":"Ahmad Rizki Pratama", "nim":"2026000001",
    //                 "email":"warga01@ktp.iae.id" } }
    // =========================================================
    public function mapToLocalRole(string $rawToken, array $payload): SsoUser
    {
        $tokenType = $payload['token_type'] ?? 'user';

        // Struktur payload berbeda antara M2M dan User token
        if ($tokenType === 'm2m') {
            // M2M: sub = api_key, data app ada di payload['app']
            $subject  = $payload['sub'] ?? null;
            $email    = $payload['app']['client_id'] . '@m2m.iae.internal'; // email sintetis
            $fullName = $payload['app']['name']   ?? null;
            $nim      = $payload['app']['team']   ?? null; // pakai team sebagai identifier
        } else {
            // User: sub = email, data profil ada di payload['profile']
            $profile  = (array) ($payload['profile'] ?? []);
            $subject  = $payload['sub'] ?? null;
            $email    = $profile['email']  ?? $subject;
            $fullName = $profile['name']   ?? null;
            $nim      = $profile['nim']    ?? null;
        }

        $expiresAt = isset($payload['exp'])
            ? \Carbon\Carbon::createFromTimestamp($payload['exp'])
            : now()->addHour();

        if (! $subject || ! $email) {
            throw new RuntimeException('Payload JWT tidak lengkap (sub/email kosong)');
        }

        // --- Logika penentuan role lokal ---
        // Karena JWT dari Pa Eki tidak menyertakan role,
        // kita tentukan sendiri berdasarkan aturan bisnis:
        //
        //  - token_type = 'm2m'     → admin (akses service-to-service)
        //  - email domain tertentu  → bisa dikustomisasi
        //  - default                → tenant (penyewa)
        //
        // Kamu bisa modifikasi logika ini sesuai kebutuhan aplikasi.
        $roleId = $this->resolveLocalRoleId($email, $tokenType, $payload);

        if (! $subject) {
            throw new RuntimeException('Payload JWT tidak lengkap (sub kosong)');
        }

        $roleId = $this->resolveLocalRoleId($email, $tokenType, $payload);

        // Upsert: kalau user sudah ada, update datanya; kalau belum, buat baru
        $ssoUser = SsoUser::updateOrCreate(
            ['sso_subject' => $subject],
            [
                'email'            => $email,
                'full_name'        => $fullName,
                'nim'              => $nim,   // NIM dari profile (bukan NIK)
                'token_type'       => $tokenType,
                'sso_payload'      => $payload,
                'local_role_id'    => $roleId,
                'last_jwt_token'   => $rawToken,
                'token_expires_at' => $expiresAt,
                'last_login_at'    => now(),
            ]
        );

        Log::info('[SSO] User dipetakan ke role lokal', [
            'email'      => $email,
            'role_id'    => $roleId,
            'token_type' => $tokenType,
        ]);

        return $ssoUser->load('localRole');
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Ambil public key JWKS dari server Pa Eki.
     * Di-cache selama 1 jam agar tidak request ulang setiap verify.
     */
    private function getPublicKeys(): array
    {
        return Cache::remember('iae_jwks', 3600, function () {
            $response = Http::get("{$this->baseUrl}/api/v1/auth/jwks");

            if (! $response->successful()) {
                throw new RuntimeException('Gagal mengambil JWKS dari server SSO');
            }

            Log::info('[SSO] JWKS berhasil diambil dari server');

            return $response->json();
        });
    }

    /**
     * Tentukan role lokal berdasarkan data JWT.
     * Logika ini bisa dikustomisasi sesuai kebutuhan bisnis aplikasi sewa.
     */
    private function resolveLocalRoleId(string $email, string $tokenType, array $payload): int
    {
        // M2M token → selalu admin
        if ($tokenType === 'm2m') {
            return LocalRole::where('name', 'admin')->value('id');
        }

        // Kamu bisa tambahkan logika custom di sini.
        // Contoh: email tertentu jadi owner
        // if (str_ends_with($email, '@properti.id')) {
        //     return LocalRole::where('name', 'owner')->value('id');
        // }

        // Default: semua user login biasa → role tenant (penyewa)
        return LocalRole::where('name', 'tenant')->value('id');
    }
}