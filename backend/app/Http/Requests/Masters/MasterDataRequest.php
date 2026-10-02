<?php

namespace App\Http\Requests\Masters;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for master-data forms. Authorization is enforced by route permission middleware.
 */
abstract class MasterDataRequest extends FormRequest
{
    /**
     * Validated data, minus `is_test` unless the user may work with test data.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('is_test', $data)) {
            if ($this->user()->can('testdata.include')) {
                $data['is_test'] = (bool) $data['is_test'];
            } else {
                unset($data['is_test']);
            }
        }

        return $data;
    }
}
