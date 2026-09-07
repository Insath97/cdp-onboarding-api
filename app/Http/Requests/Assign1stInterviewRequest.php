<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class Assign1stInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to_user_id' => 'required|integer|exists:users,id',
            'assigned_date'       => 'required|date|date_format:Y-m-d',
            'comments'            => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to_user_id.required' => 'Please select a user to assign the 1st interview to.',
            'assigned_to_user_id.exists'   => 'The selected assigned user does not exist.',
            'assigned_date.required'       => 'Please provide the assignment date.',
            'assigned_date.date'           => 'The assignment date must be a valid date.',
            'assigned_date.date_format'    => 'The assignment date must match the format YYYY-MM-DD.',
            'comments.required'            => 'Please provide assignment comments/instructions.',
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
