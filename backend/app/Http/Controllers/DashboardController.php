<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\DashboardService;
use App\Enums\RoleCode;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Role dashboards (§35). Users with several roles can switch with ?as=ROLE.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboards): View
    {
        $user = $request->user();
        $available = $dashboards->available($user);
        abort_if($available === [], 403);

        $requested = RoleCode::tryFrom((string) $request->query('as'));
        $role = in_array($requested, $available, true) ? $requested : $available[0];

        $view = match ($role) {
            RoleCode::SUPER_ADMIN, RoleCode::ADMIN => 'admin',
            RoleCode::EE => 'ee',
            RoleCode::AE => 'ae',
            RoleCode::JE => 'je',
            RoleCode::CONTRACTOR => 'contractor',
            default => 'reporter',
        };

        return view("dashboard.{$view}", [
            'user' => $user,
            'role' => $role,
            'available' => $available,
            'd' => $dashboards->build($user, $role),
        ]);
    }
}
