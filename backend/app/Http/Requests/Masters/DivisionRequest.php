<?php

namespace App\Http\Requests\Masters;

use Illuminate\Validation\Rule;

class DivisionRequest extends MasterDataRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('divisions', 'code')->ignore($this->route('division'))],
            'name' => ['required', 'string', 'max:150'],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_test' => ['sometimes', 'boolean'],
        ];
    }
}
