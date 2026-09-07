<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApproveFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_date' => 'required|date|date_format:Y-m-d',
            'comments'      => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'approval_date.required'    => 'Please provide the final approval date.',
            'approval_date.date'        => 'The approval date must be a valid date.',
            'approval_date.date_format' => 'The approval date must match the format YYYY-MM-DD.',
            'comments.required'         => 'Please provide final approval comments/remarks.',
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
