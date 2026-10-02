<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Single entry point for writing the append-only audit trail.
 */
class AuditLogger
{
    private bool $muted = false;

    private ?string $requestId = null;

    public function __construct(private readonly ?Request $request = null) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $comment = null,
        ?User $actor = null,
        ?int $deviceId = null,
    ): ?AuditLog {
        if ($this->muted) {
            return null;
        }

        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'system',
            'role_code' => $actor?->primaryRoleCode()?->value,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'comment' => $comment,
            'ip_address' => $this->request?->ip(),
            'user_agent' => Str::limit((string) $this->request?->userAgent(), 250, ''),
            'device_id' => $deviceId,
            'request_id' => $this->requestId(),
            'is_test' => (bool) ($subject?->getAttributes()['is_test'] ?? false),
        ]);
    }

    /**
     * Suppress auditing while running a callback (bulk seeding only).
     */
    public function muted(callable $callback): mixed
    {
        $previous = $this->muted;
        $this->muted = true;

        try {
            return $callback();
        } finally {
            $this->muted = $previous;
        }
    }

    private function requestId(): string
    {
        return $this->requestId ??= $this->request?->header('X-Request-Id') && Str::isUuid($this->request->header('X-Request-Id'))
            ? $this->request->header('X-Request-Id')
            : (string) Str::uuid();
    }
}
