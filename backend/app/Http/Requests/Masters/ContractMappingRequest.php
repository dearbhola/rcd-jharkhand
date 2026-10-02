<?php

namespace App\Http\Requests\Masters;

use App\Http\Requests\Concerns\ConvertsKilometres;
use Illuminate\Validation\Rule;

class ContractMappingRequest extends MasterDataRequest
{
    use ConvertsKilometres;

    protected array $kmFields = ['start_km' => 'start_chainage_m', 'end_km' => 'end_chainage_m'];

    public function rules(): array
    {
        return [
            'road_id' => ['required', Rule::exists('roads', 'id')],
            'road_section_id' => ['nullable', Rule::exists('road_sections', 'id')->where('road_id', $this->input('road_id'))],
            'start_chainage_m' => ['nullable', 'integer', 'min:0', 'required_with:end_chainage_m'],
            'end_chainage_m' => ['nullable', 'integer', 'required_with:start_chainage_m'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
