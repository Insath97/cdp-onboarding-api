<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReassignReportingManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reporting_manager_id' => 'nullable|integer|exists:employees,id',
            'effective_from'       => 'required|date|date_format:Y-m-d',
            'reason'               => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reporting_manager_id.exists'   => 'The selected reporting manager does not exist.',
            'effective_from.required'       => 'Effective start date is required.',
            'effective_from.date'           => 'Effective start date must be a valid date.',
            'effective_from.date_format'    => 'Effective start date must match format YYYY-MM-DD.',
            'reason.max'                    => 'Reason must not exceed 500 characters.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $employeeId = $this->route('id');
            if ($employeeId && $this->reporting_manager_id == $employeeId) {
                $validator->errors()->add('reporting_manager_id', 'An employee cannot be their own reporting manager.');
            }
        });
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
