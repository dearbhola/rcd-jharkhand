<?php

namespace App\Domain\Workflow\Guards;

use App\Domain\Evidence\EvidenceService;
use App\Domain\Workflow\Guard;
use App\Domain\Workflow\TransitionContext;

/** Validates attached evidence for a context (repair | inspection); effects store it. */
class EvidenceRequirements implements Guard
{
    public function __construct(
        private readonly EvidenceService $evidence,
        private readonly string $context,
    ) {}

    public function check(TransitionContext $ctx): void
    {
        if ($ctx->isSystem()) {
            return;
        }

        $ctx->evidence = $this->evidence->validateBatch($this->context, $ctx->input->files);
    }
}
