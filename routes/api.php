<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health Check endpoint
Route::get('/health-check', function () {
    return response()->json(['message' => 'CDP Connect API is working!']);
});

/* version 1 routes */
require __DIR__ . '/v1.php';

