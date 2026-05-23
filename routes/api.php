<?php

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tenants', TenantController::class);
Route::apiResource('contracts', ContractController::class);
