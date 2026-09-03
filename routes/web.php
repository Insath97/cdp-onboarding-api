<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Root endpoint
Route::get('/', function () {
    return response()->json([
        'message' => 'CDP Connect web application API',
        'version' => '1.0.0',
        'health' => '/health-check',
        'baseUrl' => env('APP_URL', 'http://localhost'),
    ]);
});

// Comprehensive health check
Route::get('/health', function () {
    $currentDateTime = now();
    $status = [
        'status' => 'healthy',
        'date' => $currentDateTime->toDateString(),
        'time' => $currentDateTime->toTimeString(),
        'service' => 'CDP Web Application System API',
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
