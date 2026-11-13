<?php

namespace App\Http\Controllers\V1\External;

use App\Enums\CandidateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCandidateRequest;
use App\Models\Candidate;
use App\Traits\ActivityLogTrait;
use App\Traits\CandidateSmsTrait;
use App\Traits\FileUploadTrait;

class PortalController extends Controller
{
    use ActivityLogTrait, CandidateSmsTrait, FileUploadTrait;

    /**
     * Public web portal CV submission (no auth / no API key header).
     */
    public function store(CreateCandidateRequest $request)
    {
        try {
            $data = $request->validated();

            // 1. Upload CV file using FileUploadTrait
            $cvPath = $this->handleFileUpload($request, 'cv_file', null, 'candidates', 'cv_'.time());
            if ($cvPath) {
                $data['cv_path'] = $cvPath;
            }

            // 2. Set submission parameters (anonymous portal submission)
            $data['status'] = CandidateStatus::PENDING->value;
            $data['source'] = Candidate::SOURCE_DIRECT;
            $data['submitted_at'] = now();
            $data['uploaded_by'] = null;
            $data['channel_head_employee_id'] = null;

            // 3. Save Candidate
            $candidate = Candidate::create($data);
            $candidate->load(['designation', 'channelHead:id,first_name,last_name']);

            // 4. Send Document Upload Link SMS
            $this->sendDocumentUploadSms($candidate);

            // 5. Activity log (resolves to Guest/System user for public requests)
            $this->logActivity(
                'CREATE',
                'Candidate',
                "Portal CV submission: {$candidate->full_name} for designation ID {$candidate->designation_id}",
                [
                    'candidate_id' => $candidate->id,
                    'nic_number' => $candidate->nic_number,
                    'phone_number' => $candidate->phone_number,
                    'source' => 'portal',
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate submitted successfully',
                'data' => $candidate,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit candidate',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
