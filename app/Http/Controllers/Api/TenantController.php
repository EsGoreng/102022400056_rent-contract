<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    /**
     * Display a listing of all tenants with their contracts count.
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::with('contracts')->orderByDesc('created_at')->get();

        return $this->successResponse(
            TenantResource::collection($tenants),
            'Data retrieved successfully',
            $this->apiMeta()
        );
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::create($request->validated());

        return $this->successResponse(
            new TenantResource($tenant->load('contracts')),
            'Tenant created successfully',
            $this->apiMeta()
        );
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('contracts');

        return $this->successResponse(
            new TenantResource($tenant),
            'Data retrieved successfully',
            $this->apiMeta()
        );
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(StoreTenantRequest $request, Tenant $tenant): JsonResponse
    {
        if (! $tenant->update($request->validated())) {
            return $this->errorResponse(
                'Unable to update tenant',
                500,
                null,
                $this->apiMeta()
            );
        }

        return $this->successResponse(
            new TenantResource($tenant->load('contracts')),
            'Tenant updated successfully',
            $this->apiMeta()
        );
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        if (! $tenant->delete()) {
            return $this->errorResponse(
                'Unable to delete tenant',
                500,
                null,
                $this->apiMeta()
            );
        }

        return $this->successResponse(
            new TenantResource($tenant),
            'Tenant deleted successfully',
            $this->apiMeta()
        );
    }

    private function apiMeta(): array
    {
        return [
            'service_name' => 'Rent-Contract-Service',
            'api_version' => 'v1',
        ];
    }
}
