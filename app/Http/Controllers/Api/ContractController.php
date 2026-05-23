<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractController extends Controller
{
    /**
     * Display a listing of all contracts with eager loaded tenant.
     */
    public function index(): AnonymousResourceCollection
    {
        $contracts = Contract::with('tenant')->orderByDesc('created_at')->get();

        return ContractResource::collection($contracts);
    }

    /**
     * Store a newly created contract in storage.
     */
    public function store(StoreContractRequest $request): JsonResource
    {
        $contract = Contract::create($request->validated());

        return new ContractResource($contract->load('tenant'));
    }

    /**
     * Display the specified contract.
     */
    public function show(Contract $contract): JsonResource
    {
        $contract->load('tenant');

        return new ContractResource($contract);
    }

    /**
     * Update the specified contract in storage.
     */
    public function update(StoreContractRequest $request, Contract $contract): JsonResource
    {
        $contract->update($request->validated());

        return new ContractResource($contract->load('tenant'));
    }

    /**
     * Remove the specified contract from storage.
     */
    public function destroy(Contract $contract): JsonResource
    {
        $contract->delete();

        return new ContractResource($contract);
    }
}
