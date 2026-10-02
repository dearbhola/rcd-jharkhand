<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\PasswordPolicy;
use App\Http\Requests\Auth\MobileRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a staff user. Authorization: route middleware (user.create / user.update)
 * plus UserAdminService rules for Super Admin accounts.
 */
class UserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'employee_code' => $this->employee_code ? strtoupper(trim($this->employee_code)) : null,
            'contractor_id' => $this->contractor_id ?: null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'mobile' => [...MobileRule::rules(), Rule::unique('users', 'mobile')->ignore($id)],
            'employee_code' => ['nullable', 'string', 'max:30', 'alpha_dash', Rule::unique('users', 'employee_code')->ignore($id)],
            'designation' => ['nullable', 'string', 'max:100'],
            'contractor_id' => ['nullable', 'integer', Rule::exists('contractors', 'id')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            'password' => $id ? ['prohibited'] : ['required', 'confirmed', app(PasswordPolicy::class)->rule()],
        ];
    }

    /** @return array<string, mixed> */
    public function attributesForSave(): array
    {
        $data = $this->safe()->only(['name', 'email', 'mobile', 'employee_code', 'designation', 'contractor_id']);
        if ($this->route('user') === null) {
            $data['password'] = $this->validated('password');
        }

        return $data;
    }

    /** @return list<int> */
    public function roleIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('roles'))));
    }
}
