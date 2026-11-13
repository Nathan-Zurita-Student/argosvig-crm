<?php

return [
    [
        'key' => 'document-center',
        'name' => 'human_resources::workforce.documents',
        'route' => 'admin.human_resources.document_center.index',
        'sort' => 14,
        'icon-class' => 'icon-quote',
    ], [
        'key' => 'timekeeping',
        'name' => 'human_resources::workforce.timekeeping',
        'route' => 'admin.human_resources.timekeeping.index',
        'sort' => 15,
        'icon-class' => 'icon-activity',
    ],
    [
        'key' => 'workforce',
        'name' => 'human_resources::app.menu.employees',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 8,
        'icon-class' => 'icon-user',
    ], [
        'key' => 'vacations',
        'name' => 'human_resources::app.menu.vacations',
        'route' => 'admin.human_resources.vacations.index',
        'sort' => 9,
        'icon-class' => 'icon-activity',
    ], [
        'key' => 'service-contracts',
        'name' => 'human_resources::app.menu.contracts',
        'route' => 'admin.human_resources.contracts.index',
        'sort' => 12,
        'icon-class' => 'icon-quote',
    ], [
        'key' => 'incidents',
        'name' => 'human_resources::app.menu.incidents',
        'route' => 'admin.human_resources.incidents.index',
        'sort' => 11,
        'icon-class' => 'icon-activity',
    ], [
        'key' => 'work-sites',
        'name' => 'human_resources::app.menu.work_sites',
        'route' => 'admin.human_resources.work_sites.index',
        'sort' => 10,
        'icon-class' => 'icon-product',
    ], [
        'key' => 'work-sites.sites', 'name' => 'human_resources::app.menu.sites', 'route' => 'admin.human_resources.work_sites.index', 'sort' => 1, 'icon-class' => '',
    ], [
        'key' => 'work-sites.shifts', 'name' => 'human_resources::app.menu.shifts', 'route' => 'admin.human_resources.shifts.index', 'sort' => 2, 'icon-class' => '',
    ], [
        'key' => 'departments',
        'name' => 'human_resources::app.menu.departments',
        'route' => 'admin.human_resources.departments.index',
        'sort' => 13,
        'icon-class' => 'icon-contact',
    ],
];
