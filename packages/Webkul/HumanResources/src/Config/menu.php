<?php

/**
 * Two groups keep the sidebar short: people management and the field operation.
 * Each entry names the ACL permission that grants it; a group lists the permissions
 * of its entries and shows up while any of them is allowed.
 */
return [
    [
        'key' => 'human-resources',
        'name' => 'human_resources::app.menu.human_resources',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 8,
        'icon-class' => 'icon-user',
        'permission' => ['human-resources.employees', 'human-resources.vacations', 'human-resources.departments', 'document-center', 'timekeeping'],
    ], [
        'key' => 'human-resources.employees',
        'name' => 'human_resources::app.menu.employees',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 1,
        'icon-class' => '',
    ], [
        'key' => 'human-resources.vacations',
        'name' => 'human_resources::app.menu.vacations',
        'route' => 'admin.human_resources.vacations.index',
        'sort' => 2,
        'icon-class' => '',
    ], [
        'key' => 'human-resources.departments',
        'name' => 'human_resources::app.menu.departments',
        'route' => 'admin.human_resources.departments.index',
        'sort' => 3,
        'icon-class' => '',
    ], [
        'key' => 'human-resources.document-center',
        'name' => 'human_resources::workforce.documents',
        'route' => 'admin.human_resources.document_center.index',
        'sort' => 4,
        'icon-class' => '',
        'permission' => 'document-center',
    ], [
        'key' => 'human-resources.timekeeping',
        'name' => 'human_resources::workforce.timekeeping',
        'route' => 'admin.human_resources.timekeeping.index',
        'sort' => 5,
        'icon-class' => '',
        'permission' => 'timekeeping',
    ], [
        'key' => 'operations',
        'name' => 'human_resources::app.menu.operations',
        'route' => 'admin.human_resources.work_sites.index',
        'sort' => 9,
        'icon-class' => 'icon-location',
        'permission' => ['human-resources.work-sites', 'human-resources.shifts', 'human-resources.incidents', 'human-resources.contracts', 'checklists'],
    ], [
        'key' => 'operations.work-sites',
        'name' => 'human_resources::app.menu.sites',
        'route' => 'admin.human_resources.work_sites.index',
        'sort' => 1,
        'icon-class' => '',
        'permission' => 'human-resources.work-sites',
    ], [
        'key' => 'operations.shifts',
        'name' => 'human_resources::app.menu.shifts',
        'route' => 'admin.human_resources.shifts.index',
        'sort' => 2,
        'icon-class' => '',
        'permission' => 'human-resources.shifts',
    ], [
        'key' => 'operations.incidents',
        'name' => 'human_resources::app.menu.incidents',
        'route' => 'admin.human_resources.incidents.index',
        'sort' => 3,
        'icon-class' => '',
        'permission' => 'human-resources.incidents',
    ], [
        'key' => 'operations.contracts',
        'name' => 'human_resources::app.menu.contracts',
        'route' => 'admin.human_resources.contracts.index',
        'sort' => 4,
        'icon-class' => '',
        'permission' => 'human-resources.contracts',
    ], [
        'key' => 'operations.checklists',
        'name' => 'human_resources::checklists.title',
        'route' => 'admin.human_resources.checklists.index',
        'sort' => 5,
        'icon-class' => '',
        'permission' => 'checklists',
    ], [
        // The board columns are configured in the settings, next to the lead pipelines.
        'key' => 'settings.operations',
        'name' => 'human_resources::checklists.settings.group',
        'info' => 'human_resources::checklists.settings.group_info',
        'route' => 'admin.settings.checklist_board.edit',
        'sort' => 5,
        'icon-class' => '',
    ], [
        'key' => 'settings.operations.checklist_board',
        'name' => 'human_resources::checklists.settings.title',
        'info' => 'human_resources::checklists.settings.info',
        'route' => 'admin.settings.checklist_board.edit',
        'sort' => 1,
        'icon-class' => 'icon-kanban',
    ],
];
