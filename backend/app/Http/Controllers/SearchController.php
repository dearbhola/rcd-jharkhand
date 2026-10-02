<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\Report;
use App\Models\Road;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global search (§53). Each result group is shown only with the matching view permission;
 * reports respect report visibility. "RCD-005 14.2" jumps to reports near that chainage.
 */
class SearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $user = $request->user();
        $groups = [];
        $chainageHit = null;

        if (mb_strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            // "<road code> [km] <number>" → reports near that chainage
            if (preg_match('/^([A-Za-z0-9\-\/]+)\s+(?:km\s*)?(\d+(?:\.\d+)?)$/i', $q, $m) && $user->can('road.view')) {
                $road = Road::where('code', strtoupper($m[1]))->first();
                if ($road) {
                    $km = (float) $m[2];
                    $chainageHit = ['road' => $road, 'km' => $km,
                        'url' => route('reports.index', ['road_id' => $road->id, 'km_from' => max(0, $km - 0.5), 'km_to' => $km + 0.5])];
                }
            }

            if ($user->can('report.view') || $user->can('report.view_all')) {
                $groups['Reports'] = Report::visibleTo($user)->with('road:id,code')
                    ->where('report_no', 'like', $like)->latest('id')->limit(self::LIMIT)->get()
                    ->map(fn ($r) => [route('reports.show', $r), $r->report_no, ($r->road?->code ?? '').' km '.km($r->chainage_m), $r]);
            }
            if ($user->can('road.view')) {
                $groups['Roads'] = Road::where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('road_number', 'like', $like))
                    ->orderBy('code')->limit(self::LIMIT)->get()
                    ->map(fn ($r) => [route('roads.show', $r), $r->code, $r->name, $r]);
            }
            if ($user->can('asset.view')) {
                $groups['Assets'] = Asset::with('road:id,code')->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->orderBy('code')->limit(self::LIMIT)->get()
                    ->map(fn ($a) => [route('assets.show', $a), $a->code, $a->name.' · '.$a->road?->code, $a]);
            }
            if ($user->can('contractor.view')) {
                $groups['Contractors'] = Contractor::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('registration_no', 'like', $like))
                    ->orderBy('name')->limit(self::LIMIT)->get()
                    ->map(fn ($c) => [route('contractors.show', $c), $c->name, $c->code, $c]);
            }
            if ($user->can('contract.view')) {
                $groups['Contracts'] = Contract::with('contractor:id,name')->where(fn ($w) => $w->where('contract_no', 'like', $like)->orWhere('agreement_no', 'like', $like)->orWhere('name', 'like', $like))
                    ->orderBy('contract_no')->limit(self::LIMIT)->get()
                    ->map(fn ($c) => [route('contracts.show', $c), $c->contract_no, $c->contractor->name, $c]);
            }
            if ($user->can('user.view')) {
                $groups['Users'] = User::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like)->orWhere('employee_code', 'like', $like))
                    ->orderBy('name')->limit(self::LIMIT)->get()
                    ->map(fn ($u) => [route('admin.users.show', $u), $u->name, trim($u->employee_code.' '.$u->mobile), $u]);
            }
        }

        $groups = array_filter($groups, fn ($rows) => $rows->isNotEmpty());

        return view('search', ['q' => $q, 'groups' => $groups, 'chainageHit' => $chainageHit]);
    }
}
