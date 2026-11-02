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
use App\Http\Controllers\V1\ChannelHeadController;
use App\Http\Controllers\V1\ActivityLogController;
use App\Http\Controllers\V1\CandidateController;
use App\Http\Controllers\V1\EmployeeHierarchyController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\DatabaseController;
use App\Http\Controllers\V1\ImportController;
use App\Http\Controllers\V1\DashboardController;
use Illuminate\Support\Facades\Route;

/* public routes */

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

/* protected routes */
Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('permissions/list', [PermissionController::class, 'getPermissionList']);
    Route::apiResource('permissions', PermissionController::class);

    Route::get('roles/list', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);

    Route::patch('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Countries
    Route::prefix('countries')->group(function () {
        Route::get('list', [CountryController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [CountryController::class, 'toggleStatus']);
    });
    Route::apiResource('countries', CountryController::class);

    // Provinces
    Route::prefix('provinces')->group(function () {
        Route::get('list', [ProvinceController::class, 'getProvinceList']);
        Route::patch('{id}/toggle-status', [ProvinceController::class, 'toggleStatus']);
    });
    Route::apiResource('provinces', ProvinceController::class);

    // Zonals (Zones)
    Route::prefix('zonals')->group(function () {
        Route::get('list', [ZonalController::class, 'getZonalList']);
        Route::patch('{id}/toggle-status', [ZonalController::class, 'toggleStatus']);
    });
    Route::apiResource('zonals', ZonalController::class);

    // Regions
    Route::prefix('regions')->group(function () {
        Route::get('list', [RegionController::class, 'getRegionList']);
        Route::patch('{id}/toggle-status', [RegionController::class, 'toggleStatus']);
    });
    Route::apiResource('regions', RegionController::class);

    // Branches
    Route::prefix('branches')->group(function () {
        Route::get('list', [BranchController::class, 'getBranchList']);
        Route::patch('{id}/toggle-status', [BranchController::class, 'toggleStatus']);
    });
    Route::apiResource('branches', BranchController::class);

    // Departments
    Route::prefix('departments')->group(function () {
        Route::get('list', [DepartmentController::class, 'getDepartmentList']);
        Route::get('{id}/designations', [DepartmentController::class, 'getDesignations']);
        Route::patch('{id}/toggle-status', [DepartmentController::class, 'toggleStatus']);
    });
    Route::apiResource('departments', DepartmentController::class);

    // Designations
    Route::prefix('designations')->group(function () {
        Route::get('list', [DesignationController::class, 'getDesignationList']);
        Route::patch('{id}/toggle-status', [DesignationController::class, 'toggleStatus']);
    });
    Route::apiResource('designations', DesignationController::class);

    // Groups
    Route::prefix('groups')->group(function () {
        Route::get('list', [GroupController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [GroupController::class, 'toggleStatus']);
    });
    Route::apiResource('groups', GroupController::class);

    // Channel Wise Employments
    Route::prefix('channel-wise-employments')->group(function () {
        Route::get('list',                [ChannelWiseEmploymentController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status',[ChannelWiseEmploymentController::class, 'toggleStatus']);
        // Channel Head management
        Route::get('{id}/head',           [ChannelHeadController::class, 'getCurrentHead']);
        Route::post('{id}/head',          [ChannelHeadController::class, 'assignHead']);
        Route::get('{id}/head/history',   [ChannelHeadController::class, 'getHistory']);
    });
    Route::apiResource('channel-wise-employments', ChannelWiseEmploymentController::class);

    // Employee Hierarchy
    Route::prefix('employees')->group(function () {
        Route::get('{id}/manager-history',  [EmployeeHierarchyController::class, 'getHistory']);
        Route::post('{id}/reassign-manager', [EmployeeHierarchyController::class, 'reassignManager']);
        Route::get('{id}/hierarchy-as-of',  [EmployeeHierarchyController::class, 'getHierarchyAsOf']);
    });

    // System Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',               [NotificationController::class, 'index']);
        Route::get('unread-count',     [NotificationController::class, 'unreadCount']);
        Route::patch('{id}/read',     [NotificationController::class, 'markAsRead']);
        Route::post('mark-all-read',  [NotificationController::class, 'markAllAsRead']);
        Route::delete('{id}',         [NotificationController::class, 'destroy']);
    });

    // Activity Logs
    Route::apiResource('activity-logs', ActivityLogController::class)->only(['index', 'show']);

    // Candidates & Interview Workflow (Stage 0, 1, 2, 3)
    Route::prefix('candidates')->group(function () {
        Route::get('list',                       [CandidateController::class, 'getCandidateList']);
        Route::get('interviewers',              [CandidateController::class, 'getInterviewers']);
        Route::get('{id}/approvals',             [CandidateController::class, 'getApprovals']);
        // Stage 1
        Route::post('{id}/assign-1st-interview', [CandidateController::class, 'assign1stInterview']);
        Route::post('{id}/approve-1st-interview',[CandidateController::class, 'approve1stInterview']);
        Route::post('{id}/reject-1st-interview', [CandidateController::class, 'reject1stInterview']);
        // Stage 2
        Route::post('{id}/assign-2nd-interview', [CandidateController::class, 'assign2ndInterview']);
        Route::post('{id}/approve-2nd-interview',[CandidateController::class, 'approve2ndInterview']);
        Route::post('{id}/reject-2nd-interview', [CandidateController::class, 'reject2ndInterview']);
        // Stage 3 (Final Approval)
        Route::post('{id}/approve-final',        [CandidateController::class, 'approveFinal']);
        Route::post('{id}/reject-final',         [CandidateController::class, 'rejectFinal']);
    });
    Route::apiResource('candidates', CandidateController::class);

    // Database & Data Export
    Route::prefix('database')->group(function () {
        Route::get('export',               [DatabaseController::class, 'export']);
        Route::get('export-data',          [DatabaseController::class, 'exportData']);
        Route::get('export-table/{table}', [DatabaseController::class, 'exportTable']);
        Route::get('export-all-tables',    [DatabaseController::class, 'exportAllTables']);
    });

    // Bulk Import
    Route::prefix('import')->group(function () {
        Route::get('tables',           [ImportController::class, 'listTables']);
        Route::get('catalog',          [ImportController::class, 'index']);
        Route::get('template/{table}', [ImportController::class, 'downloadTemplate']);
        Route::post('{table}',         [ImportController::class, 'import']);
    });
});
