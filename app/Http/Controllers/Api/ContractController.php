<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    /**
     * Display a listing of all contracts with eager loaded tenant.
     */
    public function index(): JsonResponse
    {
        $contracts = Contract::with('tenant')->orderByDesc('created_at')->get();

        return $this->successResponse(
            ContractResource::collection($contracts),
            'Data retrieved successfully',
            $this->apiMeta()
        );
    }

    /**
     * Store a newly created contract in storage.
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = Contract::create($request->validated());

        return $this->successResponse(
            new ContractResource($contract->load('tenant')),
            'Contract created successfully',
            $this->apiMeta()
        );
    }

    /**
     * Display the specified contract.
     */
    public function show(Contract $contract): JsonResponse
    {
        $contract->load('tenant');

        return $this->successResponse(
            new ContractResource($contract),
            'Data retrieved successfully',
            $this->apiMeta()
        );
    }

    /**
     * Update the specified contract in storage.
     */
    public function update(StoreContractRequest $request, Contract $contract): JsonResponse
    {
        if (! $contract->update($request->validated())) {
            return $this->errorResponse(
                'Unable to update contract',
                500,
                null,
                $this->apiMeta()
            );
        }

        return $this->successResponse(
            new ContractResource($contract->load('tenant')),
            'Contract updated successfully',
            $this->apiMeta()
        );
    }

    /**
     * Remove the specified contract from storage.
     */
    public function destroy(Contract $contract): JsonResponse
    {
        if (! $contract->delete()) {
            return $this->errorResponse(
                'Unable to delete contract',
                500,
                null,
                $this->apiMeta()
            );
        }

        return $this->successResponse(
            new ContractResource($contract),
            'Contract deleted successfully',
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
