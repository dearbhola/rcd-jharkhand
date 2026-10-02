<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SlaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gis\GeometryController;
use App\Http\Controllers\Gis\LocateController;
use App\Http\Controllers\Gis\MapController;
use App\Http\Controllers\Masters\AssetController;
use App\Http\Controllers\Masters\CategoryController;
use App\Http\Controllers\Masters\ContractController;
use App\Http\Controllers\Masters\ContractorController;
use App\Http\Controllers\Masters\DivisionController;
use App\Http\Controllers\Masters\ResponsibilityController;
use App\Http\Controllers\Masters\RoadController;
use App\Http\Controllers\Masters\RoadLookupController;
use App\Http\Controllers\Masters\RoadSectionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\DelegationController;
use App\Http\Controllers\Reports\EvidenceController;
use App\Http\Controllers\Reports\ReportActionController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportPreviewController;
use App\Http\Controllers\Reports\TaskController;
use App\Http\Controllers\Reports\TaskReassignController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TestDataController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
| Guest
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    // Parked until an SMS provider is configured (setting auth.sms_otp_enabled).
    Route::middleware('feature:auth.sms_otp_enabled')->group(function () {
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'sendOtp'])->middleware('throttle:otp')->name('register.send-otp');
        Route::get('register/verify', [RegisterController::class, 'verifyForm'])->name('register.verify');
        Route::post('register/verify', [RegisterController::class, 'verify'])->middleware('throttle:login')->name('register.verify.store');

        Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.forgot');
        Route::post('forgot-password', [PasswordResetController::class, 'sendOtp'])->middleware('throttle:otp')->name('password.forgot.send');
        Route::get('reset-password', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:login')->name('password.reset.update');
    });
});

/*
| Authenticated
*/
Route::middleware(['auth', 'active', 'password.current'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('change-password', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::put('change-password', [ChangePasswordController::class, 'update'])->name('password.change.update');

    Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('test-data/toggle', [TestDataController::class, 'toggle'])->middleware('permission:testdata.include')->name('test-data.toggle');

    /*
    | Field reporting (Phase 5)
    */
    Route::middleware('permission:report.create')->group(function () {
        Route::get('reports/create', [ReportController::class, 'create'])->name('reports.create');
        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
        Route::get('reports/preview.json', [ReportPreviewController::class, 'preview'])->middleware('throttle:60,1')->name('reports.preview');
        Route::get('reports/duplicates.json', [ReportPreviewController::class, 'duplicates'])->middleware('throttle:60,1')->name('reports.duplicates');
    });
    Route::middleware('permission:report.view|report.view_all')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    });
    Route::post('reports/{report}/actions/{action}', [ReportActionController::class, 'store'])
        ->where('action', '[a-z_]+')->middleware('throttle:30,1')->name('reports.actions');
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('reports/{report}/reassign', [TaskReassignController::class, 'store'])->middleware('permission:workflow.reassign')->name('reports.reassign');

    /*
    | Leave & delegation (Phase 7)
    */
    Route::controller(DelegationController::class)->group(function () {
        Route::get('delegations', 'index')->middleware('permission:delegation.view|delegation.manage')->name('delegations.index');
        Route::get('delegations/create', 'create')->middleware('permission:delegation.manage')->name('delegations.create');
        Route::post('delegations', 'store')->middleware('permission:delegation.manage')->name('delegations.store');
        Route::get('delegations/{delegation}', 'show')->middleware('permission:delegation.view|delegation.manage')->name('delegations.show');
        Route::post('delegations/{delegation}/transfer', 'transfer')->middleware('permission:delegation.manage')->name('delegations.transfer');
        Route::post('delegations/{delegation}/end', 'end')->middleware('permission:delegation.manage')->name('delegations.end');
    });
    Route::get('evidence/{evidence}', [EvidenceController::class, 'display'])->name('evidence.display');
    Route::get('evidence/{evidence}/thumbnail', [EvidenceController::class, 'thumbnail'])->name('evidence.thumbnail');
    Route::get('evidence/{evidence}/original', [EvidenceController::class, 'original'])->name('evidence.original');

    /*
    | GIS (Phase 4)
    */
    Route::middleware('permission:road.view')->group(function () {
        Route::get('map', [MapController::class, 'index'])->name('map.index');
        Route::get('map/sections.json', [MapController::class, 'sections'])->name('map.sections');
        Route::get('map/assets.json', [MapController::class, 'assets'])->name('map.assets');
        Route::get('map/reports.json', [MapController::class, 'reports'])->name('map.reports');
        Route::get('map/sections/{roadSection}.json', [MapController::class, 'sectionInfo'])->name('map.section-info');
        Route::get('gis/locate', [LocateController::class, 'index'])->name('gis.locate');
        Route::get('gis/resolve.json', [LocateController::class, 'resolve'])->middleware('throttle:120,1')->name('gis.resolve');
    });
    Route::controller(GeometryController::class)->middleware('permission:gis.manage')->group(function () {
        Route::get('roads/{road}/geometry', 'edit')->name('gis.geometry.edit');
        Route::put('roads/{road}/geometry', 'update')->name('gis.geometry.update');
        Route::post('roads/{road}/geometry/import', 'import')->name('gis.geometry.import');
        Route::get('roads/{road}/geometry.geojson', 'exportRoad')->name('gis.export.road');
        Route::get('gis/export.geojson', 'exportAll')->name('gis.export.all');
        Route::post('roads/{road}/chainage-markers', 'storeMarker')->name('gis.markers.store');
        Route::delete('chainage-markers/{marker}', 'destroyMarker')->name('gis.markers.destroy');
    });

    /*
    | Master data (Phase 3)
    */
    Route::controller(DivisionController::class)->group(function () {
        Route::get('divisions', 'index')->middleware('permission:division.view')->name('divisions.index');
        Route::middleware('permission:division.manage')->group(function () {
            Route::get('divisions/create', 'create')->name('divisions.create');
            Route::post('divisions', 'store')->name('divisions.store');
            Route::get('divisions/{division}/edit', 'edit')->name('divisions.edit');
            Route::put('divisions/{division}', 'update')->name('divisions.update');
            Route::get('divisions/{division}/sub-divisions/create', 'createSub')->name('sub-divisions.create');
            Route::post('sub-divisions', 'storeSub')->name('sub-divisions.store');
            Route::get('sub-divisions/{subDivision}/edit', 'editSub')->name('sub-divisions.edit');
            Route::put('sub-divisions/{subDivision}', 'updateSub')->name('sub-divisions.update');
        });
    });

    Route::controller(CategoryController::class)->middleware('permission:category.manage')->group(function () {
        Route::get('categories', 'index')->name('categories.index');
        Route::post('asset-types', 'storeAssetType')->name('asset-types.store');
        Route::put('asset-types/{assetType}', 'updateAssetType')->name('asset-types.update');
        Route::post('asset-types/{assetType}/categories', 'storeIssueCategory')->name('issue-categories.store');
        Route::put('issue-categories/{issueCategory}', 'updateIssueCategory')->name('issue-categories.update');
        Route::post('severities', 'storeSeverity')->name('severities.store');
        Route::put('severities/{severity}', 'updateSeverity')->name('severities.update');
        Route::post('road-categories', 'storeRoadCategory')->name('road-categories.store');
        Route::put('road-categories/{roadCategory}', 'updateRoadCategory')->name('road-categories.update');
    });

    Route::controller(RoadController::class)->group(function () {
        Route::get('roads', 'index')->middleware('permission:road.view')->name('roads.index');
        Route::get('roads/create', 'create')->middleware('permission:road.create')->name('roads.create');
        Route::post('roads', 'store')->middleware('permission:road.create')->name('roads.store');
        Route::get('roads/{road}', 'show')->middleware('permission:road.view')->name('roads.show');
        Route::get('roads/{road}/edit', 'edit')->middleware('permission:road.update')->name('roads.edit');
        Route::put('roads/{road}', 'update')->middleware('permission:road.update')->name('roads.update');
    });
    Route::get('roads/{road}/sections.json', [RoadLookupController::class, 'sections'])->middleware('permission:road.view')->name('roads.sections.json');

    Route::controller(RoadSectionController::class)->group(function () {
        Route::get('road-sections/{roadSection}', 'show')->middleware('permission:road_section.view')->name('road-sections.show');
        Route::middleware('permission:road_section.manage')->group(function () {
            Route::post('roads/{road}/sections', 'store')->name('road-sections.store');
            Route::get('road-sections/{roadSection}/edit', 'edit')->name('road-sections.edit');
            Route::put('road-sections/{roadSection}', 'update')->name('road-sections.update');
            Route::post('road-sections/{roadSection}/split', 'split')->name('road-sections.split');
        });
    });

    Route::controller(AssetController::class)->group(function () {
        Route::get('assets', 'index')->middleware('permission:asset.view')->name('assets.index');
        Route::get('assets/create', 'create')->middleware('permission:asset.create')->name('assets.create');
        Route::post('assets', 'store')->middleware('permission:asset.create')->name('assets.store');
        Route::get('assets/{asset}', 'show')->middleware('permission:asset.view')->name('assets.show');
        Route::get('assets/{asset}/edit', 'edit')->middleware('permission:asset.update')->name('assets.edit');
        Route::put('assets/{asset}', 'update')->middleware('permission:asset.update')->name('assets.update');
    });

    Route::controller(ContractorController::class)->group(function () {
        Route::get('contractors', 'index')->middleware('permission:contractor.view')->name('contractors.index');
        Route::get('contractors/create', 'create')->middleware('permission:contractor.create')->name('contractors.create');
        Route::post('contractors', 'store')->middleware('permission:contractor.create')->name('contractors.store');
        Route::get('contractors/{contractor}', 'show')->middleware('permission:contractor.view')->name('contractors.show');
        Route::get('contractors/{contractor}/edit', 'edit')->middleware('permission:contractor.update')->name('contractors.edit');
        Route::put('contractors/{contractor}', 'update')->middleware('permission:contractor.update')->name('contractors.update');
    });

    Route::controller(ContractController::class)->group(function () {
        Route::get('contracts', 'index')->middleware('permission:contract.view')->name('contracts.index');
        Route::get('contracts/create', 'create')->middleware('permission:contract.create')->name('contracts.create');
        Route::post('contracts', 'store')->middleware('permission:contract.create')->name('contracts.store');
        Route::get('contracts/{contract}', 'show')->middleware('permission:contract.view')->name('contracts.show');
        Route::get('contracts/{contract}/edit', 'edit')->middleware('permission:contract.update')->name('contracts.edit');
        Route::put('contracts/{contract}', 'update')->middleware('permission:contract.update')->name('contracts.update');
        Route::post('contracts/{contract}/mappings', 'storeMapping')->middleware('permission:contract.map')->name('contracts.mappings.store');
        Route::put('contract-mappings/{mapping}/end', 'endMapping')->middleware('permission:contract.map')->name('contracts.mappings.end');
        Route::post('contracts/{contract}/documents', 'storeDocument')->middleware('permission:contract.update')->name('contracts.documents.store');
        Route::get('contract-documents/{document}', 'downloadDocument')->middleware('permission:contract.view')->name('contracts.documents.download');
    });

    Route::controller(ResponsibilityController::class)->group(function () {
        Route::get('responsibility', 'index')->middleware('permission:responsibility.view')->name('responsibility.index');
        Route::get('responsibility/assign', 'create')->middleware('permission:responsibility.manage')->name('responsibility.create');
        Route::post('responsibility', 'store')->middleware('permission:responsibility.manage')->name('responsibility.store');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->middleware('permission:user.view')->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->middleware('permission:user.create')->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create')->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.view')->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:user.update')->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:user.update')->name('users.update');
        Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->middleware('permission:user.deactivate')->name('users.status');
        Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->middleware('permission:user.update')->name('users.password');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.view')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.manage')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:role.view')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.manage')->name('roles.update');

        Route::controller(SlaController::class)->middleware('permission:sla.manage')->group(function () {
            Route::get('sla', 'index')->name('sla.index');
            Route::post('sla/rules', 'storeRule')->name('sla.rules.store');
            Route::put('sla/rules/{rule}', 'updateRule')->name('sla.rules.update');
            Route::post('sla/escalations', 'storeEscalation')->name('sla.escalations.store');
            Route::put('sla/escalations/{rule}', 'updateEscalation')->name('sla.escalations.update');
        });

        Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:settings.manage')->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');

        Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');
        Route::get('audit/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit.view')->name('audit.show');
    });
});
