<?php

namespace App\Http\Requests\Masters;

use App\Http\Requests\Concerns\ConvertsKilometres;
use Illuminate\Validation\Rule;

class RoadRequest extends MasterDataRequest
{
    use ConvertsKilometres;

    protected array $kmFields = ['start_km' => 'start_chainage_m', 'end_km' => 'end_chainage_m'];

    protected function afterKilometres(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9\-\/]*$/', Rule::unique('roads', 'code')->ignore($this->route('road'))],
            'name' => ['required', 'string', 'max:255'],
            'road_number' => ['nullable', 'string', 'max:30'],
            'road_category_id' => ['nullable', Rule::exists('road_categories', 'id')->where('is_active', true)],
            'division_id' => ['required', Rule::exists('divisions', 'id')],
            'sub_division_id' => ['nullable', Rule::exists('sub_divisions', 'id')->where('division_id', $this->input('division_id'))],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'start_location' => ['nullable', 'string', 'max:255'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'start_chainage_m' => ['required', 'integer', 'min:0'],
            'end_chainage_m' => ['required', 'integer', 'gt:start_chainage_m'],
            'status' => ['required', Rule::in(['active', 'inactive', 'under_construction'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'external_ref' => ['nullable', 'string', 'max:100'],
            'is_test' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Use capital letters, digits, "-" or "/".', 'sub_division_id.exists' => 'The sub-division must belong to the selected division.'];
    }

    public function payload(): array
    {
        $data = parent::payload();
        // length_m tracks the drawn geometry; until then it is the chainage extent.
        $data['length_m'] = $data['end_chainage_m'] - $data['start_chainage_m'];

        return $data;
    }
}
