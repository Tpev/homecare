<?php

namespace App\Livewire\Forms;

use App\Models\PartnerProfile;
use App\Support\PartnerOptions;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PartnerProfileForm extends Form
{
    public string $business_name = '';

    public string $website = '';

    public string $description = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public array $categories = [];

    public array $coverage_states = [];

    public string $coverage_type = 'specific_areas';

    public string $coverage_areas = '';

    public string $customer_notes = '';

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => [
                'required', 'string', 'max:30', 'regex:/^\+?[0-9() .\-]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = preg_replace('/\D/', '', (string) $value);
                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('Enter a valid contact phone number.');
                    }
                },
            ],
            'categories' => ['required', 'array', 'min:1', 'max:'.count(PartnerOptions::categories())],
            'categories.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PartnerOptions::categories()))],
            'coverage_states' => ['required', 'array', 'min:1', 'max:'.count(PartnerOptions::states())],
            'coverage_states.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PartnerOptions::states()))],
            'coverage_type' => ['required', Rule::in(['entire_states', 'specific_areas'])],
            'coverage_areas' => ['required_if:coverage_type,specific_areas', 'nullable', 'string', 'max:1000'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function rulesForStep(int $step): array
    {
        $fields = match ($step) {
            1 => ['business_name', 'website', 'description', 'contact_name', 'contact_email', 'contact_phone'],
            2 => ['categories', 'categories.*', 'coverage_states', 'coverage_states.*', 'coverage_type', 'coverage_areas', 'customer_notes'],
            default => array_keys($this->rules()),
        };

        return array_intersect_key($this->rules(), array_flip($fields));
    }

    public function validateStep(int $step): array
    {
        return $this->validate($this->rulesForStep($step));
    }

    public function validate($rules = null, $messages = [], $attributes = [])
    {
        $this->normalize();

        return parent::validate($rules, $messages, array_merge([
            'business_name' => 'business name',
            'contact_name' => 'contact name',
            'contact_email' => 'contact email',
            'contact_phone' => 'contact phone',
            'categories' => 'service categories',
            'categories.*' => 'service category',
            'coverage_states' => 'coverage states',
            'coverage_states.*' => 'coverage state',
            'coverage_type' => 'coverage type',
            'coverage_areas' => 'cities, counties or ZIP codes',
            'customer_notes' => 'customer notes',
        ], $attributes));
    }

    /** Only partner-editable fields are returned. */
    public function normalizedData(): array
    {
        $data = $this->validate();
        foreach (['website', 'description', 'coverage_areas', 'customer_notes'] as $field) {
            $data[$field] = $data[$field] === '' ? null : $data[$field];
        }
        if ($data['coverage_type'] === 'entire_states') {
            $data['coverage_areas'] = null;
        }

        return $data;
    }

    public function setProfile(PartnerProfile $profile): void
    {
        foreach (['business_name', 'website', 'description', 'contact_name', 'contact_email',
            'contact_phone', 'coverage_type', 'coverage_areas', 'customer_notes'] as $field) {
            $this->{$field} = (string) ($profile->{$field} ?? '');
        }
        $this->categories = $profile->categories ?? [];
        $this->coverage_states = $profile->coverage_states ?? [];
    }

    private function normalize(): void
    {
        foreach (['business_name', 'website', 'description', 'contact_name', 'contact_email',
            'contact_phone', 'coverage_type', 'coverage_areas', 'customer_notes'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
        $this->contact_email = mb_strtolower($this->contact_email);
        if ($this->website !== '' && ! preg_match('/^[a-z][a-z0-9+.-]*:/i', $this->website)) {
            $this->website = 'https://'.$this->website;
        }
        $this->categories = array_values(array_map(
            fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $this->categories,
        ));
        $this->coverage_states = array_values(array_map(
            fn (mixed $value): mixed => is_string($value) ? strtoupper(trim($value)) : $value,
            $this->coverage_states,
        ));
    }
}
