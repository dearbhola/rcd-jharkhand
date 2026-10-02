<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load(['roles', 'contractor']);

        return view('profile.show', [
            'user' => $user,
            'permissions' => $user->isSuperAdmin() ? collect(['All permissions (Super Admin)']) : $user->permissionKeys()->sort()->values(),
        ]);
    }
}
