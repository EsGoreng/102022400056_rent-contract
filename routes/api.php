<?php

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // PUBLIK — tidak butuh token apapun
    Route::post('/auth/sso/login', [SsoController::class, 'login']);

    // PROTECTED — butuh central.jwt (token API kamu yang lama)
    Route::middleware('api.key')->group(function () {
        Route::apiResource('tenants', TenantController::class);
        Route::apiResource('contracts', ContractController::class);
    });

    // PROTECTED — butuh jwt.verify (token dari SSO Pa Eki)
    Route::middleware('jwt.verify')->group(function () {
        Route::get('/auth/sso/me', [SsoController::class, 'me']);
    });
});