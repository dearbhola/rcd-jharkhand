<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Analytics\Exporter;
use App\Support\TestDataMode;
use Illuminate\Http\Request;

/**
 * Official exports (§42): test data is excluded unless an authorised user explicitly asks to
 * include it — then every exported page is marked "TEST DATA INCLUDED — NOT AN OFFICIAL REPORT".
 * On screen, the user's normal test-data toggle applies.
 *
 * @return array{0: ?string, 1: bool} [export format or null, whether test data is included]
 */
trait HandlesExports
{
    protected function exportMode(Request $request): array
    {
        $format = $request->query('export');
        $mode = app(TestDataMode::class);

        if ($format === null) {
            return [null, $mode->includesTestData()];
        }

        abort_unless(in_array($format, Exporter::FORMATS, true), 400);
        abort_unless($request->user()->can('analytics.export'), 403);

        $include = $request->boolean('include_test') && $request->user()->can('testdata.include');
        $mode->include($include);
        $mode->forceExclude(! $include);

        return [$format, $include];
    }
}
