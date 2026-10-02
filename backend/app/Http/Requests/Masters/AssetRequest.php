<?php

namespace App\Http\Requests\Masters;

use App\Http\Requests\Concerns\ConvertsKilometres;
use Illuminate\Validation\Rule;

class AssetRequest extends MasterDataRequest
{
    use ConvertsKilometres;

    protected array $kmFields = ['chainage_km' => 'chainage_m', 'end_chainage_km' => 'end_chainage_m'];

    protected function afterKilometres(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\-\/_]*$/', Rule::unique('assets', 'code')->ignore($this->route('asset'))],
            'asset_type_id' => ['required', Rule::exists('asset_types', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'road_id' => ['required', Rule::exists('roads', 'id')],
            'chainage_m' => ['required', 'integer', 'min:0'],
            'end_chainage_m' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'under_repair', 'decommissioned'])],
            'external_ref' => ['nullable', 'string', 'max:100'],
            'is_test' => ['sometimes', 'boolean'],
        ];
    }
}
