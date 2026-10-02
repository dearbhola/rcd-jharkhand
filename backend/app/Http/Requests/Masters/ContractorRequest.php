<?php

namespace App\Http\Requests\Masters;

use App\Http\Requests\Auth\MobileRule;
use Illuminate\Validation\Rule;

class ContractorRequest extends MasterDataRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
            'pan' => $this->pan ? strtoupper(trim($this->pan)) : null,
            'gstin' => $this->gstin ? strtoupper(trim($this->gstin)) : null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('contractor');

        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('contractors', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:50', Rule::unique('contractors', 'registration_no')->ignore($id)],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', MobileRule::REGEX],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended', 'blacklisted', 'inactive'])],
            'is_test' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['pan.regex' => 'PAN must look like ABCDE1234F.', 'gstin.regex' => 'GSTIN format is invalid.'];
    }
}
