<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RejectFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_date' => 'required|date|date_format:Y-m-d',
            'comments'       => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_date.required'    => 'Please provide the final rejection date.',
            'rejection_date.date'        => 'The rejection date must be a valid date.',
            'rejection_date.date_format' => 'The rejection date must match the format YYYY-MM-DD.',
            'comments.required'          => 'Please state the reason for final rejection.',
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
