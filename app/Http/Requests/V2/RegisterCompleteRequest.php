<?php

namespace App\Http\Requests\V2;

use App\Models\Country;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterCompleteRequest extends FormRequest
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
        // Check how many active countries exist
        $activeCountriesCount = Country::where('is_active', true)->count();

        $rules = [
            'verification_token' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];

        // country_id is required only if there's more than one active country
        if ($activeCountriesCount > 1) {
            $rules['country_id'] = ['required', 'integer', 'exists:countries,id'];
        } else {
            $rules['country_id'] = ['nullable', 'integer', 'exists:countries,id'];
        }

        // language_id is always optional
        $rules['language_id'] = ['nullable', 'integer', 'exists:languages,id'];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_id.required' => __('The country field is required when multiple countries are available.'),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Validate that country_id is active if provided
            if ($this->has('country_id') && $this->country_id !== null) {
                $country = Country::find($this->country_id);
                if ($country && ! $country->is_active) {
                    $validator->errors()->add(
                        'country_id',
                        __('locale.validation.country_not_available')
                    );
                }
            }

            // Validate that language_id is active if provided
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
}
