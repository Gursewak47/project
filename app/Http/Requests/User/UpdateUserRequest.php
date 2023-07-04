<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
            ],
        ];

        if (request()->has('change_password') && request()->change_password === true) {
            $rules =  array_merge($rules, [
                'old_password' => [
                    'required',
                ],
                'password' => [
                    'required',
                    'confirmed',
                    'min:6',
                    'max:255',
                ],
            ]);
        }

        if (request()->has('change_email') && request()->change_email === true) {
            $rules =  array_merge($rules, [
                'new_email' => [
                    'required',
                    'email',
                ],
            ]);
        }

        return  $rules;
    }
}
