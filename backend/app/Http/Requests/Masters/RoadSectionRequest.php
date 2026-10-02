<?php

namespace App\Http\Requests\Masters;

use App\Http\Requests\Concerns\ConvertsKilometres;
use App\Models\RoadSection;
use Illuminate\Validation\Rule;

class RoadSectionRequest extends MasterDataRequest
{
    use ConvertsKilometres;

    protected array $kmFields = ['start_km' => 'start_chainage_m', 'end_km' => 'end_chainage_m'];

    protected function afterKilometres(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        /** @var RoadSection|null $section */
        $section = $this->route('roadSection');
        $roadId = $section?->road_id ?? $this->route('road')?->id;

        return [
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('road_sections', 'code')->where('road_id', $roadId)->ignore($section)],
            'name' => ['nullable', 'string', 'max:255'],
            'division_id' => ['required', Rule::exists('divisions', 'id')],
            'sub_division_id' => ['nullable', Rule::exists('sub_divisions', 'id')->where('division_id', $this->input('division_id'))],
            'start_chainage_m' => ['required', 'integer', 'min:0'],
            'end_chainage_m' => ['required', 'integer', 'gt:start_chainage_m'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
