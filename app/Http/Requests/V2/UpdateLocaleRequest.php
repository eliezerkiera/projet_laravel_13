<?php

namespace App\Http\Requests\V2;

use App\Models\Country;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'reset_to_auto' => ['boolean'],
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
            'country_id.integer' => 'The selected country is invalid.',
            'language_id.exists' => 'The selected language is invalid.',
            'language_id.integer' => 'The selected language is invalid.',
            'reset_to_auto.boolean' => 'The reset_to_auto field must be true or false.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // At least one of country_id, language_id or reset_to_auto must be present
            if (! $this->hasAtLeastOneOf(['country_id', 'language_id', 'reset_to_auto'])) {
                $validator->errors()->add(
                    'fields',
                    __('locale.validation.fields_required')
                );
            }

            // reset_to_auto cannot be combined with country_id or language_id
            if ($this->boolean('reset_to_auto')) {
                if ($this->has('country_id') && $this->country_id !== null) {
                    $validator->errors()->add(
                        'reset_to_auto',
                        __('locale.validation.reset_to_auto_conflict')
                    );

                    return;
                }
                if ($this->has('language_id') && $this->language_id !== null) {
                    $validator->errors()->add(
                        'reset_to_auto',
                        __('locale.validation.reset_to_auto_conflict')
                    );

                    return;
                }
            }

            // Additional validation for country_id (must be active)
            if ($this->has('country_id') && $this->country_id !== null) {
                $country = Country::find($this->country_id);
                if ($country && ! $country->is_active) {
                    $validator->errors()->add(
                        'country_id',
                        __('locale.validation.country_not_available')
                    );
                }
            }

            // Additional validation for language_id (must be active if is_active column exists)
            if ($this->has('language_id') && $this->language_id !== null) {
                $language = Language::find($this->language_id);
                if ($language && ! $language->is_active) {
                    $validator->errors()->add(
                        'language_id',
                        'The selected language is invalid.'
                    );
                }
            }
        });
    }

    /**
     * Check if any of the specified keys are present in the request.
     */
    public function hasAtLeastOneOf(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }
}
