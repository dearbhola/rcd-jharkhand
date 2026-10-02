<?php

namespace App\Http\Requests\Auth;

/**
 * Indian 10-digit mobile number.
 */
final class MobileRule
{
    public const REGEX = 'regex:/^[6-9][0-9]{9}$/';

    /** @return list<string> */
    public static function rules(): array
    {
        return ['required', 'string', self::REGEX];
    }
}
