<?php

namespace App\Traits;

use App\Models\Candidate;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

trait CandidateSmsTrait
{
    use ActivityLogTrait;

    /**
     * Standard CDP Empire footer appended to all candidate SMS messages.
     */
    protected function getCdpFooter(): string
    {
        return "Thank you for choosing CDP Empire (Pvt) Ltd.\n\n".
            "For any inquiries:\n".
            "Hotline: +94 114 007 007\n".
            'Website: https://cdp.lk/';
    }

    /**
     * Send document upload link SMS to candidate upon CV submission.
     */
    public function sendDocumentUploadSms(Candidate $candidate): bool
    {
        try {
            $smsService = app(SmsService::class);
            $uploadUrl = $candidate->getDocumentUploadUrl();
            $designationName = $candidate->designation?->name;
            $designationPart = $designationName ? " for the {$designationName} position" : '';
            $message = "Dear {$candidate->first_name}, your CV{$designationPart} has been received (Ref: {$candidate->candidate_code}). Please upload your required documents using this link: {$uploadUrl}. Note: Link is active while your application is pending.\n\n".$this->getCdpFooter();

            $sent = $smsService->sendSms($candidate->phone_number, $message);

            $this->logActivity(
                'SEND_SMS',
                'Candidate',
                $sent
                    ? "Document upload SMS sent to candidate {$candidate->full_name} ({$candidate->phone_number})"
                    : "Failed to send document upload SMS to candidate {$candidate->full_name} ({$candidate->phone_number})",
                [
                    'candidate_id'   => $candidate->id,
                    'candidate_code' => $candidate->candidate_code,
                    'channel'        => 'dialog_sms',
                    'status'         => $sent ? 'sent' : 'failed',
                ],
                $sent ? 'info' : 'warning'
            );

            return $sent;
        } catch (\Throwable $th) {
            Log::warning("Failed to send document upload SMS for candidate {$candidate->id}: {$th->getMessage()}");

            $this->logActivity(
                'SEND_SMS_FAILED',
                'Candidate',
                "Document upload SMS exception for candidate {$candidate->full_name}: {$th->getMessage()}",
                [
                    'candidate_id'   => $candidate->id,
                    'candidate_code' => $candidate->candidate_code,
                    'channel'        => 'dialog_sms',
                    'status'         => 'failed',
                ],
                'warning'
            );

            return false;
        }
    }
}
