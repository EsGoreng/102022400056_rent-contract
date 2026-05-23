<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantController extends Controller
{
    /**
     * Display a listing of all tenants with their contracts count.
     */
    public function index(): AnonymousResourceCollection
    {
        $tenants = Tenant::with('contracts')->orderByDesc('created_at')->get();

        return TenantResource::collection($tenants);
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(StoreTenantRequest $request): JsonResource
    {
        $tenant = Tenant::create($request->validated());

        return new TenantResource($tenant->load('contracts'));
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): JsonResource
    {
        $tenant->load('contracts');

        return new TenantResource($tenant);
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(StoreTenantRequest $request, Tenant $tenant): JsonResource
    {
        $tenant->update($request->validated());

        return new TenantResource($tenant->load('contracts'));
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant): JsonResource
    {
        $tenant->delete();

        return new TenantResource($tenant);
    }
}
