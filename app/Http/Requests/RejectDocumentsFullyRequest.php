<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RejectDocumentsFullyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Support 'reason' or 'rejection_reason' as aliases for 'comments'
        if (! $this->has('comments')) {
            if ($this->has('reason')) {
                $this->merge(['comments' => $this->reason]);
            } elseif ($this->has('rejection_reason')) {
                $this->merge(['comments' => $this->rejection_reason]);
            }
        }

        // Default rejection_date to current date if omitted
        if (! $this->has('rejection_date')) {
            $this->merge(['rejection_date' => now()->toDateString()]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comments'                   => 'required|string|max:1000',
            'reason'                     => 'nullable|string|max:1000',
            'rejection_reason'           => 'nullable|string|max:1000',
            'rejection_date'             => 'required|date|date_format:Y-m-d',
            'rejections'                 => 'nullable|array',
            'rejections.*.document_id'   => 'required_with:rejections|integer|exists:candidate_documents,id',
            'rejections.*.reason'        => 'required_with:rejections|string|max:500',
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'comments.required'               => 'Please provide a reason for the full rejection.',
            'rejection_date.required'         => 'Please provide the rejection date.',
            'rejection_date.date'             => 'The rejection date must be a valid date.',
            'rejection_date.date_format'      => 'The rejection date must match the format YYYY-MM-DD.',
            'rejections.*.document_id.exists' => 'One or more rejected document IDs do not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
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
