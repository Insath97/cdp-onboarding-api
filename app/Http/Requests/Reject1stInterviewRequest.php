<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class Reject1stInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interview_date' => 'required|date|date_format:Y-m-d',
            'comments'       => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'interview_date.required'    => 'Please provide the rejection/interview date.',
            'interview_date.date'        => 'The date must be a valid date.',
            'interview_date.date_format' => 'The date must match the format YYYY-MM-DD.',
            'comments.required'          => 'Please state the reason for rejecting the 1st interview.',
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
