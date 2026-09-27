<?php

namespace App\Http\Requests\V2;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocaleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['nullable', 'exists:countries,id'],
            'language_id' => ['nullable', 'exists:languages,id'],
            'reset_to_auto' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_id.exists' => 'The selected country is invalid.',
            'language_id.exists' => 'The selected language is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    protected function prepareForValidation(): void
    {
        // Ensure reset_to_auto is boolean
        $this->merge([
            'reset_to_auto' => $this->boolean('reset_to_auto'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['nullable', 'exists:countries,id'],
            'language_id' => ['nullable', 'exists:languages,id'],
            'reset_to_auto' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // At least one of country_id or language_id must be provided (unless reset_to_auto is true)
            if (! $this->boolean('reset_to_auto') && ! $this->filled('country_id') && ! $this->filled('language_id')) {
                $validator->errors()->add('fields', 'At least one of country_id or language_id must be provided.');
            }
        });
    }
}
