<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\RoleController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\ProvinceController;
use App\Http\Controllers\V1\RegionController;
use App\Http\Controllers\V1\ZonalController;
use App\Http\Controllers\V1\BranchController;
use App\Http\Controllers\V1\DesignationController;
use App\Http\Controllers\V1\CountryController;
use App\Http\Controllers\V1\GroupController;
use App\Http\Controllers\V1\ChannelWiseEmploymentController;
use Illuminate\Support\Facades\Route;

/* public routes */

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

/* protected routes */
Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::get('permissions/list', [PermissionController::class, 'getPermissionList']);
    Route::apiResource('permissions', PermissionController::class);

    Route::get('roles/list/', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);

    Route::patch('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Countries
    Route::apiResource('countries', CountryController::class);
    Route::prefix('countries')->group(function () {
        Route::patch('{id}/toggle-status', [CountryController::class, 'toggleStatus']);
        Route::get('list', [CountryController::class, 'getActiveList']);
    });

    // Provinces
    Route::apiResource('provinces', ProvinceController::class);
    Route::prefix('provinces')->group(function () {
        Route::patch('{id}/toggle-status', [ProvinceController::class, 'toggleStatus']);
        Route::get('list', [ProvinceController::class, 'getProvinceList']);
    });

    // Zonals (Zones)
    Route::apiResource('zonals', ZonalController::class);
    Route::prefix('zonals')->group(function () {
        Route::patch('{id}/toggle-status', [ZonalController::class, 'toggleStatus']);
        Route::get('list', [ZonalController::class, 'getZonalList']);
    });

    // Regions
    Route::apiResource('regions', RegionController::class);
    Route::prefix('regions')->group(function () {
        Route::patch('{id}/toggle-status', [RegionController::class, 'toggleStatus']);
        Route::get('list', [RegionController::class, 'getRegionList']);
    });

    // Branches
    Route::apiResource('branches', BranchController::class);
    Route::prefix('branches')->group(function () {
        Route::patch('{id}/toggle-status', [BranchController::class, 'toggleStatus']);
        Route::get('list', [BranchController::class, 'getBranchList']);
    });

    // Departments
    Route::apiResource('departments', DepartmentController::class);
    Route::prefix('departments')->group(function () {
        Route::get('{id}/designations', [DepartmentController::class, 'getDesignations']);
        Route::patch('{id}/toggle-status', [DepartmentController::class, 'toggleStatus']);
    });

    // Designations
    Route::apiResource('designations', DesignationController::class);
    Route::prefix('designations')->group(function () {
        Route::get('list', [DesignationController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [DesignationController::class, 'toggleStatus']);
    });

    // Groups
    Route::apiResource('groups', GroupController::class);
    Route::prefix('groups')->group(function () {
        Route::get('list', [GroupController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [GroupController::class, 'toggleStatus']);
    });

    // Channel Wise Employments
    Route::prefix('channel-wise-employments')->group(function () {
        Route::get('list', [ChannelWiseEmploymentController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [ChannelWiseEmploymentController::class, 'toggleStatus']);
    });
    Route::apiResource('channel-wise-employments', ChannelWiseEmploymentController::class);
});
