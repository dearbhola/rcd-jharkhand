<?php

/*
 * Sidebar menu. An item is shown when its route exists AND the user holds
 * any of its permissions. `active` is a route-name pattern.
 */
return [
    ['section' => null, 'items' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'dashboard', 'can' => ['dashboard.view'], 'active' => 'dashboard'],
        ['label' => 'Map', 'icon' => 'map', 'route' => 'map.index', 'can' => ['road.view'], 'active' => 'map.*'],
        ['label' => 'Locate a point', 'icon' => 'crosshair', 'route' => 'gis.locate', 'can' => ['road.view'], 'active' => 'gis.locate'],
    ]],
    ['section' => 'Field Work', 'items' => [
        ['label' => 'Report Damage', 'icon' => 'camera', 'route' => 'reports.create', 'can' => ['report.create'], 'active' => 'reports.create'],
        ['label' => 'My Tasks', 'icon' => 'list-check', 'route' => 'tasks.index', 'can' => ['report.validate', 'report.review', 'repair.submit', 'approval.approve'], 'active' => 'tasks.*'],
        ['label' => 'Reports', 'icon' => 'exclamation-triangle', 'route' => 'reports.index', 'can' => ['report.view', 'report.view_all'], 'active' => 'reports.index|reports.show'],
        ['label' => 'Delegations', 'icon' => 'people', 'route' => 'delegations.index', 'can' => ['delegation.view', 'delegation.manage'], 'active' => 'delegations.*'],
    ]],
    ['section' => 'Masters', 'items' => [
        ['label' => 'Roads', 'icon' => 'signpost-split', 'route' => 'roads.index', 'can' => ['road.view'], 'active' => 'roads.*|road-sections.*'],
        ['label' => 'Assets', 'icon' => 'bricks', 'route' => 'assets.index', 'can' => ['asset.view'], 'active' => 'assets.*'],
        ['label' => 'Contractors', 'icon' => 'building', 'route' => 'contractors.index', 'can' => ['contractor.view'], 'active' => 'contractors.*'],
        ['label' => 'Contracts', 'icon' => 'file-earmark-text', 'route' => 'contracts.index', 'can' => ['contract.view'], 'active' => 'contracts.*'],
        ['label' => 'Responsibility', 'icon' => 'diagram-3', 'route' => 'responsibility.index', 'can' => ['responsibility.view'], 'active' => 'responsibility.*'],
        ['label' => 'Divisions', 'icon' => 'bank', 'route' => 'divisions.index', 'can' => ['division.view'], 'active' => 'divisions.*|sub-divisions.*'],
        ['label' => 'Categories', 'icon' => 'tags', 'route' => 'categories.index', 'can' => ['category.manage'], 'active' => 'categories.*|severities.*|asset-types.*'],
    ]],
    ['section' => 'Analysis', 'items' => [
        ['label' => 'Contractor Performance', 'icon' => 'graph-up', 'route' => 'performance.index', 'can' => ['performance.view'], 'active' => 'performance.*'],
        ['label' => 'Reports & Exports', 'icon' => 'file-earmark-bar-graph', 'route' => 'analytics.index', 'can' => ['analytics.view'], 'active' => 'analytics.*'],
    ]],
    ['section' => 'Administration', 'items' => [
        ['label' => 'Users', 'icon' => 'person-gear', 'route' => 'admin.users.index', 'can' => ['user.view'], 'active' => 'admin.users.*'],
        ['label' => 'Roles & Permissions', 'icon' => 'shield-lock', 'route' => 'admin.roles.index', 'can' => ['role.view'], 'active' => 'admin.roles.*'],
        ['label' => 'Workflow', 'icon' => 'bezier2', 'route' => 'admin.workflows.index', 'can' => ['workflow.manage'], 'active' => 'admin.workflows.*'],
        ['label' => 'SLA & Escalation', 'icon' => 'stopwatch', 'route' => 'admin.sla.index', 'can' => ['sla.manage'], 'active' => 'admin.sla.*'],
        ['label' => 'System Settings', 'icon' => 'sliders', 'route' => 'admin.settings.index', 'can' => ['settings.manage'], 'active' => 'admin.settings.*'],
        ['label' => 'Audit Log', 'icon' => 'journal-text', 'route' => 'admin.audit.index', 'can' => ['audit.view'], 'active' => 'admin.audit.*'],
    ]],
];
