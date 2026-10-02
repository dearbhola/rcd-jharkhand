<?php

namespace App\Http\Requests\Concerns;

/**
 * Forms take chainage in km (e.g. 14.250); the database stores integer metres.
 * Declare `protected array $kmFields = ['start_km' => 'start_chainage_m', ...]`.
 */
trait ConvertsKilometres
{
    protected function prepareForValidation(): void
    {
        $converted = [];
        foreach ($this->kmFields as $kmField => $metreField) {
            $value = $this->input($kmField);
            if ($value === null || $value === '') {
                continue;
            }
            $converted[$metreField] = is_numeric($value) ? (int) round(((float) $value) * 1000) : $value;
        }
        $this->merge($converted);
        $this->afterKilometres();
    }

    protected function afterKilometres(): void {}

    /** Validation messages refer to the km field the user typed in. */
    public function attributes(): array
    {
        return collect($this->kmFields)->mapWithKeys(fn ($m, $k) => [$m => str_replace('_', ' ', $k)])->all();
    }
}
