<?php

namespace App\Http\Requests\Masters;

use Illuminate\Validation\Rule;

class SubDivisionRequest extends MasterDataRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'division_id' => ['required', Rule::exists('divisions', 'id')],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('sub_divisions', 'code')->ignore($this->route('subDivision'))],
            'name' => ['required', 'string', 'max:150'],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'is_active' => ['required', 'boolean'],
            'is_test' => ['sometimes', 'boolean'],
        ];
    }
}
