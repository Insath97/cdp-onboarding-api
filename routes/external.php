<?php

use App\Http\Controllers\V1\External\CandidateDocumentPortalController;
use App\Http\Controllers\V1\External\PortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External / Public Portal Routes (v1)
|--------------------------------------------------------------------------
|
| Routes consumed by external frontends and the candidate document portal.
| These endpoints do not use JWT authentication:
| - POST portal/candidates is secured with the X-CDP-PORTAL-KEY header.
| - The document portal endpoints are guarded by the candidate's
|   document upload token and SMS OTP verification.
|
*/

Route::prefix('v1')->group(function () {
    // Web portal CV submission (public, secured with X-CDP-PORTAL-KEY header)
    Route::post('portal/candidates', [PortalController::class, 'store'])->middleware('portal.key', 'throttle:10,1');

    // Candidate Document Portal (Token & OTP Guarded)
    Route::prefix('portal/documents')->group(function () {
        Route::get('validate/{token}', [CandidateDocumentPortalController::class, 'validateToken']);
        Route::post('request-otp', [CandidateDocumentPortalController::class, 'requestOtp'])->middleware('throttle:5,1');
        Route::post('verify-otp', [CandidateDocumentPortalController::class, 'verifyOtp'])->middleware('throttle:5,1');
        Route::get('checklist/{token}', [CandidateDocumentPortalController::class, 'getDocumentChecklist']);
        Route::post('upload', [CandidateDocumentPortalController::class, 'uploadDocument']);
        Route::post('submit-all', [CandidateDocumentPortalController::class, 'submitAllDocuments']);
    });
});
