<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $candidateId = $this->route('candidate') ?? $this->route('id');

        return [
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'nic_number'        => [
                'required',
                'string',
                'max:50',
                Rule::unique('candidates')->where(function ($query) {
                    return $query->where('designation_id', $this->designation_id)
                                 ->where('phone_number', $this->phone_number);
                })->ignore($candidateId),
            ],
            'phone_number'      => 'required|string|max:30',
            'secondary_number'  => 'nullable|string|max:30',
            'designation_id'    => 'required|integer|exists:designations,id',
            'expected_salary'   => 'required|numeric|min:0',
            'cv_file'           => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'uploader_comments' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'     => 'Please provide the candidate\'s first name.',
            'last_name.required'      => 'Please provide the candidate\'s last name.',
            'nic_number.required'     => 'Please provide the NIC number.',
            'nic_number.unique'       => 'A candidate with this NIC number, phone number, and designation has already been submitted.',
            'phone_number.required'   => 'Please provide the primary phone number.',
            'designation_id.required' => 'Please select a designation.',
            'designation_id.exists'   => 'The selected designation does not exist.',
            'expected_salary.required' => 'Please state the expected salary.',
            'expected_salary.numeric' => 'Expected salary must be a valid number.',
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
