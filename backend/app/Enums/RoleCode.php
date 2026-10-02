<?php

namespace App\Enums;

/**
 * System role codes. Permissions are database-driven; these codes only identify
 * roles that business rules refer to (e.g. "the mapped JE").
 * Declaration order is the precedence used when a user holds several roles.
 */
enum RoleCode: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case EE = 'EE';
    case AE = 'AE';
    case JE = 'JE';
    case CONTRACTOR = 'CONTRACTOR';
    case RCD_STAFF = 'RCD_STAFF';
    case CITIZEN = 'CITIZEN';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::EE => 'Executive Engineer',
            self::AE => 'Assistant Engineer',
            self::JE => 'Junior Engineer',
            self::CONTRACTOR => 'Contractor',
            self::RCD_STAFF => 'RCD Staff',
            self::CITIZEN => 'Citizen',
        };
    }

    /** Roles held through road-section responsibility mapping. */
    public static function engineerRoles(): array
    {
        return [self::JE, self::AE, self::EE];
    }

    public function isEngineer(): bool
    {
        return in_array($this, self::engineerRoles(), true);
    }
}
