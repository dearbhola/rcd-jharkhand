<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Http\Middleware\ApplyTestDataMode;
use App\Support\TestDataMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestDataController extends Controller
{
    public function toggle(Request $request, TestDataMode $mode, AuditLogger $audit): RedirectResponse
    {
        $include = ! $mode->includesTestData();
        $request->session()->put(ApplyTestDataMode::SESSION_KEY, $include);
        $audit->log('testdata.toggled', null, null, ['include' => $include]);

        return back()->with('info', $include ? 'Test data is now included in your views.' : 'Test data is now hidden.');
    }
}
