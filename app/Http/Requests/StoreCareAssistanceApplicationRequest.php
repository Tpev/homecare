<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareAssistanceApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('care-assistance.index').'#apply';
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'email', 'phone', 'city', 'zip', 'hardship_details'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        if (isset($normalized['email'])) {
            $normalized['email'] = strtolower($normalized['email']);
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:120'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255'],
            'phone' => [
                'bail', 'required', 'string', 'max:30', 'regex:/^\+?[0-9()\s.\-]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $digits = preg_replace('/\D/', '', $value);

                    if (strlen($digits) < 10 || strlen($digits) > 15) {
                        $fail('Please enter a phone number with 10 to 15 digits.');
                    }
                },
            ],
            'relationship' => ['bail', 'required', 'string', Rule::in(array_keys(config('care_assistance.relationship_options', [])))],
            'city' => ['bail', 'required', 'string', 'max:120'],
            'zip' => ['bail', 'required', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'care_needs' => ['bail', 'required', 'array', 'min:1', 'max:6'],
            'care_needs.*' => ['bail', 'required', 'string', 'distinct', Rule::in(array_keys(config('care_assistance.care_need_options', [])))],
            'weekly_hours' => ['bail', 'required', 'string', Rule::in(array_keys(config('care_assistance.weekly_hour_options', [])))],
            'start_timeframe' => ['bail', 'required', 'string', Rule::in(array_keys(config('care_assistance.start_timeframe_options', [])))],
            'hardship_details' => ['bail', 'required', 'string', 'max:3000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter an email address so we can contact you.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Please enter a phone number so we can contact you.',
            'phone.regex' => 'Please enter a valid phone number.',
            'relationship.required' => 'Please tell us who needs care.',
            'relationship.in' => 'Please choose one of the listed relationships.',
            'city.required' => 'Please enter the city where care is needed.',
            'zip.required' => 'Please enter the ZIP code where care is needed.',
            'zip.regex' => 'Please enter a five-digit ZIP code or ZIP+4, such as 27601 or 27601-1234.',
            'care_needs.required' => 'Please choose at least one type of care.',
            'care_needs.array' => 'Please choose from the listed types of care.',
            'care_needs.min' => 'Please choose at least one type of care.',
            'care_needs.max' => 'Please choose from the listed types of care.',
            'care_needs.*.in' => 'Please choose from the listed types of care.',
            'care_needs.*.distinct' => 'Please select each type of care only once.',
            'weekly_hours.required' => 'Please choose your estimated weekly hours, or select Not sure yet.',
            'weekly_hours.in' => 'Please choose one of the listed weekly hour ranges.',
            'start_timeframe.required' => 'Please tell us when you would like care to begin.',
            'start_timeframe.in' => 'Please choose one of the listed start timeframes.',
            'hardship_details.required' => 'Please briefly describe the financial difficulty you are facing.',
            'hardship_details.max' => 'Please keep your explanation to 3,000 characters or fewer.',
            'consent.accepted' => 'Please confirm your consent before submitting your application.',
            'website.max' => 'We could not submit this application. Please leave the website field empty and try again.',
            'website.string' => 'We could not submit this application. Please try again.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'phone' => 'phone number',
            'relationship' => 'relationship',
            'city' => 'city',
            'zip' => 'ZIP code',
            'care_needs' => 'types of care',
            'care_needs.*' => 'type of care',
            'weekly_hours' => 'weekly hours',
            'start_timeframe' => 'start timeframe',
            'hardship_details' => 'explanation',
        ];
    }
}
