<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Evidence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams evidence through authorisation; files are never publicly addressable.
 */
class EvidenceController extends Controller
{
    public function display(Evidence $evidence): StreamedResponse
    {
        Gate::authorize('view-evidence', $evidence);

        // Until processing finishes (or for unprocessed videos), the original is served to viewers of the report.
        $path = $evidence->display_path ?? $evidence->original_path;

        return $this->stream($evidence, $path, $evidence->display_path ? ($evidence->kind === Evidence::KIND_PHOTO ? 'image/jpeg' : 'video/mp4') : $evidence->mime_type);
    }

    public function thumbnail(Evidence $evidence): StreamedResponse
    {
        Gate::authorize('view-evidence', $evidence);
        abort_unless($evidence->thumbnail_path, 404);

        return $this->stream($evidence, $evidence->thumbnail_path, 'image/jpeg');
    }

    public function original(Evidence $evidence, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('view-original-evidence', $evidence);
        $audit->log('evidence.original_downloaded', $evidence->report, null, ['evidence_id' => $evidence->id]);

        return Storage::disk($evidence->disk)->download($evidence->original_path, basename($evidence->original_path), [
            'Content-Type' => $evidence->mime_type,
            'X-Content-SHA256' => $evidence->sha256,
        ]);
    }

    private function stream(Evidence $evidence, string $path, string $mime): StreamedResponse
    {
        return Storage::disk($evidence->disk)->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
