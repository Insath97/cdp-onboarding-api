<?php

namespace App\Http\Requests;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'nic_number'        => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $latestCandidate = Candidate::where('nic_number', $value)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if (! $latestCandidate) {
                        return;
                    }

                    $status = $latestCandidate->status instanceof CandidateStatus
                        ? $latestCandidate->status
                        : CandidateStatus::tryFrom($latestCandidate->status);

                    // 1. Block if application is currently active/in-progress
                    $isInProgress = $status
                        ? $status->isInProgress()
                        : in_array($latestCandidate->status, CandidateStatus::inProgressStatuses(), true);

                    if ($isInProgress) {
                        $statusLabel = $status ? $status->label() : ucwords(str_replace('_', ' ', (string) $latestCandidate->status));
                        $fail("A candidate with NIC '{$value}' currently has an active application in progress (Status: {$statusLabel}). A new CV cannot be uploaded until the current application reaches final approval or rejection.");
                        return;
                    }

                    // 2. Block if within 1-month cooling-off period
                    $resolvedAt = $latestCandidate->stage_updated_at ?? $latestCandidate->updated_at ?? $latestCandidate->created_at;
                    if ($resolvedAt && $resolvedAt->gt(now()->subMonth())) {
                        $eligibleDate = $resolvedAt->copy()->addMonth()->format('Y-m-d');
                        $statusLabel = $status ? $status->label() : ucwords(str_replace('_', ' ', (string) $latestCandidate->status));
                        $fail("A candidate with NIC '{$value}' was previously {$statusLabel} on {$resolvedAt->format('Y-m-d')}. A new CV cannot be uploaded within the 1-month cooling-off period. You can re-upload after {$eligibleDate}.");
                    }
                },
            ],
            'phone_number'      => 'required|string|max:30',
            'secondary_number'  => 'nullable|string|max:30',
            'designation_id'    => 'required|integer|exists:designations,id',
            'expected_salary'   => 'required|numeric|min:0',
            'cv_file'           => 'required|file|mimes:pdf,doc,docx|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document|max:5120',
            'source'            => ['sometimes', 'string', Rule::in([Candidate::SOURCE_DIRECT, Candidate::SOURCE_REFERRAL])],
            'uploader_comments' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'     => 'Please provide the candidate\'s first name.',
            'last_name.required'      => 'Please provide the candidate\'s last name.',
            'nic_number.required'     => 'Please provide the NIC number.',
            'phone_number.required'   => 'Please provide the primary phone number.',
            'designation_id.required' => 'Please select a designation.',
            'designation_id.exists'   => 'The selected designation does not exist.',
            'expected_salary.required' => 'Please state the expected salary.',
            'expected_salary.numeric' => 'Expected salary must be a valid number.',
            'cv_file.required'        => 'Please upload the candidate\'s CV file.',
            'cv_file.mimes'           => 'The CV file must be a PDF, DOC, or DOCX document.',
            'cv_file.max'             => 'The CV file size must not exceed 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();
        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return ['field' => $field, 'messages' => $messages];
        })->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors'  => $fieldErrors,
        ], 422));
    }
}
