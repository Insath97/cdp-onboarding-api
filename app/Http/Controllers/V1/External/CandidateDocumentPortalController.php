<?php

namespace App\Http\Controllers\V1\External;

use App\Enums\CandidateStatus;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\DocumentType;
use App\Services\SmsService;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CandidateDocumentPortalController extends Controller
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Validate upload token and verify candidate status is pending.
     *
     * GET /api/v1/portal/documents/validate/{token}
     */
    public function validateToken(string $token): JsonResponse
    {
        try {
            $candidate = Candidate::with('designation:id,name')
                ->where('document_upload_token', $token)
                ->first();

            if (! $candidate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid or expired document upload link.',
                ], 404);
            }

            // The link is ONLY accessible when status is pending (or document_rejected for re-upload)
            $allowedStatuses = [
                CandidateStatus::PENDING,
                CandidateStatus::DOCUMENT_REJECTED,
            ];

            if (! in_array($candidate->status, $allowedStatuses, true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "This document submission link is no longer active. Current application status: {$candidate->status->label()}.",
                ], 403);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Upload session is active.',
                'data'    => [
                    'candidate_code' => $candidate->candidate_code,
                    'first_name'     => $candidate->first_name,
                    'last_name'      => $candidate->last_name,
                    'masked_phone'   => $this->maskPhoneNumber($candidate->phone_number),
                    'designation'    => $candidate->designation?->name,
                    'status'         => $candidate->status->value,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to validate upload session.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify NIC number and send 6-digit OTP to candidate's mobile.
     *
     * POST /api/v1/portal/documents/request-otp
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'token'      => 'required|string',
            'nic_number' => 'required|string',
        ]);

        try {
            $candidate = Candidate::where('document_upload_token', $request->token)->first();

            if (! $candidate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid or expired upload link.',
                ], 404);
            }

            if (! in_array($candidate->status, [CandidateStatus::PENDING, CandidateStatus::DOCUMENT_REJECTED], true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Document upload link is no longer active.',
                ], 403);
            }

            // Verify entered NIC matches candidate record
            if (strtoupper(trim($request->nic_number)) !== strtoupper(trim($candidate->nic_number))) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The entered NIC number does not match our application records.',
                ], 422);
            }

            // Generate 6-digit OTP
            $otp = (string) random_int(100000, 999999);

            // Store in Cache for 10 minutes
            Cache::put("cand_doc_otp_{$candidate->document_upload_token}", [
                'otp'        => $otp,
                'nic_number' => $candidate->nic_number,
            ], now()->addMinutes(10));

            // Send OTP via SmsService
            try {
                $smsService = app(SmsService::class);
                $smsMessage = "Your OTP for document upload verification is {$otp}. Valid for 10 minutes. CDP Onboarding";
                $smsService->sendSms($candidate->phone_number, $smsMessage);
            } catch (\Throwable $th) {
                Log::warning("Failed to send OTP SMS to candidate {$candidate->id}: {$th->getMessage()}");
            }

            $responseData = [
                'masked_phone' => $this->maskPhoneNumber($candidate->phone_number),
            ];

            if (config('app.debug')) {
                $responseData['debug_otp'] = $otp;
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'OTP has been sent to your registered mobile number.',
                'data'    => $responseData,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to process OTP request.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify OTP and issue temporary upload session key.
     *
     * POST /api/v1/portal/documents/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'otp'   => 'required|string',
        ]);

        try {
            $candidate = Candidate::where('document_upload_token', $request->token)->first();

            if (! $candidate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid or expired upload link.',
                ], 404);
            }

            $cachedOtpData = Cache::get("cand_doc_otp_{$candidate->document_upload_token}");

            if (! $cachedOtpData) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'OTP has expired. Please request a new OTP.',
                ], 422);
            }

            if ((string) $cachedOtpData['otp'] !== trim((string) $request->otp)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid OTP entered. Please check and try again.',
                ], 422);
            }

            // Issue session key valid for 2 hours
            $sessionKey = Str::random(40);
            Cache::put("cand_doc_session_{$candidate->document_upload_token}", $sessionKey, now()->addHours(2));
            Cache::forget("cand_doc_otp_{$candidate->document_upload_token}");

            return response()->json([
                'status'  => 'success',
                'message' => 'OTP verified successfully.',
                'data'    => [
                    'session_key' => $sessionKey,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to verify OTP.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get document checklist for candidate.
     *
     * GET /api/v1/portal/documents/checklist/{token}
     */
    public function getDocumentChecklist(Request $request, string $token): JsonResponse
    {
        try {
            $sessionKey = $request->header('X-Upload-Session') ?? $request->query('session_key');
            $candidate = $this->resolveCandidateFromSession($token, $sessionKey);

            if ($candidate instanceof JsonResponse) {
                return $candidate;
            }

            $documentTypes = DocumentType::active()
                ->orderBy('is_required', 'desc')
                ->orderBy('document_name', 'asc')
                ->get();

            $existingDocs = CandidateDocument::where('candidate_id', $candidate->id)
                ->get()
                ->keyBy('document_type_id');

            $checklist = $documentTypes->map(function ($docType) use ($existingDocs) {
                $doc = $existingDocs->get($docType->id);

                return [
                    'document_type_id'   => $docType->id,
                    'document_name'      => $docType->document_name,
                    'slug'               => $docType->slug,
                    'description'        => $docType->description,
                    'is_required'        => (bool) $docType->is_required,
                    'is_uploaded'        => (bool) $doc,
                    'uploaded_document'  => $doc ? [
                        'id'               => $doc->id,
                        'original_name'    => $doc->original_name,
                        'file_size_kb'     => $doc->file_size_kb,
                        'status'           => $doc->status,
                        'rejection_reason' => $doc->rejection_reason,
                        'file_url'         => $doc->file_url,
                    ] : null,
                ];
            });

            $totalRequired = $documentTypes->where('is_required', true)->count();
            $uploadedRequired = $checklist->where('is_required', true)->where('is_uploaded', true)->count();

            return response()->json([
                'status'  => 'success',
                'message' => 'Document checklist retrieved successfully.',
                'data'    => [
                    'candidate_code'         => $candidate->candidate_code,
                    'first_name'             => $candidate->first_name,
                    'last_name'              => $candidate->last_name,
                    'total_required'         => $totalRequired,
                    'uploaded_required'      => $uploadedRequired,
                    'is_ready_for_submission'=> $uploadedRequired >= $totalRequired,
                    'checklist'              => $checklist,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve document checklist.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Upload an individual document file.
     *
     * POST /api/v1/portal/documents/upload
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'token'            => 'required|string',
            'session_key'      => 'required|string',
            'document_type_id' => 'required|integer|exists:document_types,id',
            'file'             => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $candidate = $this->resolveCandidateFromSession($request->token, $request->session_key);

            if ($candidate instanceof JsonResponse) {
                return $candidate;
            }

            $docType = DocumentType::active()->find($request->document_type_id);

            if (! $docType) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The selected document type is not active or available.',
                ], 422);
            }

            $file = $request->file('file');
            $customPrefix = "doc_{$docType->slug}_" . time();
            $filePath = $this->handleFileUpload(
                $request,
                'file',
                null,
                "candidates/{$candidate->id}/documents",
                $customPrefix
            );

            if (! $filePath) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to save uploaded document file.',
                ], 500);
            }

            $candidateDocument = CandidateDocument::updateOrCreate(
                [
                    'candidate_id'     => $candidate->id,
                    'document_type_id' => $docType->id,
                ],
                [
                    'file_path'        => $filePath,
                    'original_name'    => $file->getClientOriginalName(),
                    'mime_type'        => $file->getClientMimeType(),
                    'file_size_kb'     => round($file->getSize() / 1024),
                    'status'           => 'pending',
                    'rejection_reason' => null,
                    'verified_by'      => null,
                    'verified_at'      => null,
                ]
            );

            return response()->json([
                'status'  => 'success',
                'message' => "Document '{$docType->document_name}' uploaded successfully.",
                'data'    => $candidateDocument->load('documentType:id,document_name,slug,is_required'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to upload document.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Submit all candidate documents and update status to 'document_submitted'.
     *
     * POST /api/v1/portal/documents/submit-all
     */
    public function submitAllDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'token'       => 'required|string',
            'session_key' => 'required|string',
        ]);

        try {
            $candidate = $this->resolveCandidateFromSession($request->token, $request->session_key);

            if ($candidate instanceof JsonResponse) {
                return $candidate;
            }

            // Ensure all mandatory documents are uploaded
            $requiredDocTypes = DocumentType::active()->required()->get();
            $uploadedDocTypeIds = CandidateDocument::where('candidate_id', $candidate->id)
                ->pluck('document_type_id')
                ->toArray();

            $missing = $requiredDocTypes->reject(fn ($dt) => in_array($dt->id, $uploadedDocTypeIds));

            if ($missing->isNotEmpty()) {
                $missingNames = $missing->pluck('document_name')->join(', ');

                return response()->json([
                    'status'  => 'error',
                    'message' => "Cannot submit. Missing mandatory documents: {$missingNames}.",
                ], 422);
            }

            // Update candidate status to document_submitted
            $candidate->status = CandidateStatus::DOCUMENT_SUBMITTED;
            $candidate->stage_updated_at = now();
            // Invalidate the upload token so link cannot be reused
            $candidate->document_upload_token = null;
            $candidate->save();

            // Clear session cache
            Cache::forget("cand_doc_session_{$request->token}");

            $this->logActivity(
                'UPDATE',
                'Candidate',
                "Candidate {$candidate->full_name} submitted onboarding documents via portal.",
                ['candidate_id' => $candidate->id, 'new_status' => CandidateStatus::DOCUMENT_SUBMITTED->value]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'All documents have been submitted successfully. Your documents are now under review by our onboarding team.',
                'data'    => [
                    'candidate_code' => $candidate->candidate_code,
                    'status'         => CandidateStatus::DOCUMENT_SUBMITTED->value,
                    'status_label'   => CandidateStatus::DOCUMENT_SUBMITTED->label(),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to complete document submission.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Resolve candidate from upload token and verify active session.
     */
    protected function resolveCandidateFromSession(string $token, ?string $sessionKey): Candidate|JsonResponse
    {
        $candidate = Candidate::where('document_upload_token', $token)->first();

        if (! $candidate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid or expired upload session link.',
            ], 404);
        }

        if (! in_array($candidate->status, [CandidateStatus::PENDING, CandidateStatus::DOCUMENT_REJECTED], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This document submission link is no longer active.',
            ], 403);
        }

        $cachedSession = Cache::get("cand_doc_session_{$token}");

        if (! $cachedSession || $cachedSession !== $sessionKey) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized or expired session. Please verify with your NIC and OTP again.',
            ], 401);
        }

        return $candidate;
    }

    /**
     * Helper to mask phone number (e.g., 0771234567 -> 077****567).
     */
    protected function maskPhoneNumber(?string $phone): string
    {
        if (! $phone || strlen($phone) < 7) {
            return '*******';
        }

        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }
}
