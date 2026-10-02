<?php

namespace App\Domain\Reporting;

/**
 * Report status codes mirrored from workflow steps (see WorkflowDefinitionSeeder).
 */
final class ReportStatus
{
    public const PENDING_VALIDATION = 'PENDING_VALIDATION';

    public const ASSIGNED = 'ASSIGNED';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const REOPENED = 'REOPENED';

    public const JE_REVIEW = 'JE_REVIEW';

    public const AE_REVIEW = 'AE_REVIEW';

    public const EE_APPROVAL = 'EE_APPROVAL';

    public const DEPARTMENT_PENDING = 'DEPARTMENT_PENDING';

    public const CLOSED = 'CLOSED';

    public const INVALID_CLOSED = 'INVALID_CLOSED';

    public const MERGED_DUPLICATE = 'MERGED_DUPLICATE';

    /** @return list<string> */
    public static function terminal(): array
    {
        return [self::CLOSED, self::INVALID_CLOSED, self::MERGED_DUPLICATE];
    }

    /** @return array<string, array{0: string, 1: string}> code => [label, bootstrap colour] */
    public static function labels(): array
    {
        return [
            self::PENDING_VALIDATION => ['Awaiting validation', 'secondary'],
            self::ASSIGNED => ['Assigned', 'info'],
            self::IN_PROGRESS => ['Repair in progress', 'primary'],
            self::REOPENED => ['Reopened', 'danger'],
            self::JE_REVIEW => ['JE review', 'warning'],
            self::AE_REVIEW => ['AE review', 'warning'],
            self::EE_APPROVAL => ['EE approval', 'warning'],
            self::DEPARTMENT_PENDING => ['Department action', 'dark'],
            self::CLOSED => ['Closed', 'success'],
            self::INVALID_CLOSED => ['Closed – invalid', 'secondary'],
            self::MERGED_DUPLICATE => ['Merged duplicate', 'secondary'],
        ];
    }
}
