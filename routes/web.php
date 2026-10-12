<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Landing page
Route::get('/', function () {
    return view('landing');
});

// Comprehensive health check
Route::get('/health', function () {
    $currentDateTime = now();
    $status = [
        'status' => 'healthy',
        'date' => $currentDateTime->toDateString(),
        'time' => $currentDateTime->toTimeString(),
        'service' => 'VECTOR Onboarding Application API',
        'components' => []
    ];

    // Check database
    try {
        DB::select('SELECT 1');
        $status['components']['database'] = 'healthy';
    } catch (\Exception $e) {
        $status['components']['database'] = 'unhealthy';
        $status['status'] = 'degraded';
    }

    return response()->json($status);
});
