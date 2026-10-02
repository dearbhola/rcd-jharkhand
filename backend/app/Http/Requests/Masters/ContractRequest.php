<?php

namespace App\Http\Requests\Masters;

use Illuminate\Validation\Rule;

class ContractRequest extends MasterDataRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['contract_no' => strtoupper(trim((string) $this->contract_no))]);
    }

    public function rules(): array
    {
        return [
            'contract_no' => ['required', 'string', 'max:50', Rule::unique('contracts', 'contract_no')->ignore($this->route('contract'))],
            'name' => ['nullable', 'string', 'max:255'],
            'contractor_id' => ['required', Rule::exists('contractors', 'id')],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')],
            'agreement_no' => ['nullable', 'string', 'max:50'],
            'agreement_date' => ['nullable', 'date'],
            'work_order_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // Maintenance responsibility depends ONLY on these two dates.
            'maintenance_start_date' => ['nullable', 'date', 'required_with:maintenance_end_date'],
            'maintenance_end_date' => ['nullable', 'date', 'required_with:maintenance_start_date', 'after_or_equal:maintenance_start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'terminated'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'is_test' => ['sometimes', 'boolean'],
        ];
    }
}
