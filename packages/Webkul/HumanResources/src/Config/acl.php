<?php

return [
    [
        'key' => 'human-resources',
        'name' => 'human_resources::app.acl.title',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 8,
    ], [
        'key' => 'human-resources.employees',
        'name' => 'human_resources::app.acl.employees',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 1,
    ], [
        'key' => 'human-resources.employees.create',
        'name' => 'human_resources::app.acl.create',
        'route' => ['admin.human_resources.employees.create', 'admin.human_resources.employees.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.employees.view',
        'name' => 'human_resources::app.acl.view',
        'route' => [
            'admin.human_resources.employees.show',
        ],
        'sort' => 2,
    ], [
        'key' => 'human-resources.employees.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.employees.edit', 'admin.human_resources.employees.update'],
        'sort' => 3,
    ], [
        'key' => 'human-resources.employees.documents',
        'name' => 'human_resources::app.acl.documents',
        'route' => [
            'admin.human_resources.employee_documents.store',
            'admin.human_resources.employee_documents.download',
            'admin.human_resources.employee_documents.delete',
        ],
        'sort' => 4,
    ], [
        'key' => 'human-resources.employees.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.employees.delete',
        'sort' => 5,
    ], [
        'key' => 'document-center',
        'name' => 'human_resources::workforce.documents',
        'route' => [
            'admin.human_resources.document_center.index',
            'admin.human_resources.document_center.show',
            'admin.human_resources.document_center.download',
            'admin.human_resources.document_center.edit',
            'admin.human_resources.document_center.update',
            'admin.human_resources.document_center.delete',
        ],
        'sort' => 7,
    ], [
        'key' => 'human-resources.document-center',
        'name' => 'human_resources::workforce.employee_files',
        'route' => [],
        'sort' => 8,
    ], [
        'key' => 'human-resources.document-center.manage',
        'name' => 'human_resources::workforce.manage',
        'route' => ['admin.human_resources.document_center.create', 'admin.human_resources.document_center.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.shared-documents',
        'name' => 'human_resources::workforce.shared',
        'route' => [],
        'sort' => 9,
    ], [
        'key' => 'human-resources.shared-documents.manage',
        'name' => 'human_resources::workforce.manage',
        'route' => ['admin.human_resources.document_center.create_shared', 'admin.human_resources.document_center.store_shared'],
        'sort' => 1,
    ], [
        'key' => 'timekeeping',
        'name' => 'human_resources::workforce.timekeeping',
        'route' => ['admin.human_resources.timekeeping.index', 'admin.human_resources.timekeeping.export'],
        'sort' => 10,
    ], [
        'key' => 'timekeeping.import',
        'name' => 'human_resources::workforce.import',
        'route' => ['admin.human_resources.timekeeping.import', 'admin.human_resources.timekeeping.template'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.vacations',
        'name' => 'human_resources::app.acl.vacations',
        'route' => 'admin.human_resources.vacations.index',
        'sort' => 3,
    ], [
        'key' => 'human-resources.vacations.create',
        'name' => 'human_resources::app.acl.create',
        'route' => ['admin.human_resources.vacations.create', 'admin.human_resources.vacations.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.vacations.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.vacations.edit', 'admin.human_resources.vacations.update'],
        'sort' => 2,
    ], [
        'key' => 'human-resources.vacations.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.vacations.delete',
        'sort' => 3,
    ], [
        'key' => 'human-resources.contracts',
        'name' => 'human_resources::app.acl.contracts',
        'route' => 'admin.human_resources.contracts.index',
        'sort' => 3,
    ], [
        'key' => 'human-resources.contracts.create',
        'name' => 'human_resources::app.acl.create',
        'route' => ['admin.human_resources.contracts.create', 'admin.human_resources.contracts.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.contracts.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.contracts.edit', 'admin.human_resources.contracts.update'],
        'sort' => 2,
    ], [
        'key' => 'human-resources.contracts.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.contracts.delete',
        'sort' => 3,
    ], [
        'key' => 'human-resources.incidents',
        'name' => 'human_resources::app.acl.incidents',
        'route' => 'admin.human_resources.incidents.index',
        'sort' => 4,
    ], [
        'key' => 'human-resources.incidents.create',
        'name' => 'human_resources::app.acl.create',
        'route' => ['admin.human_resources.incidents.create', 'admin.human_resources.incidents.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.incidents.view',
        'name' => 'human_resources::app.acl.view',
        'route' => 'admin.human_resources.incidents.show',
        'sort' => 2,
    ], [
        'key' => 'human-resources.incidents.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.incidents.edit', 'admin.human_resources.incidents.update'],
        'sort' => 3,
    ], [
        'key' => 'human-resources.incidents.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.incidents.delete',
        'sort' => 4,
    ], [
        'key' => 'human-resources.work-sites',
        'name' => 'human_resources::app.acl.work_sites',
        'route' => 'admin.human_resources.work_sites.index',
        'sort' => 5,
    ], [
        'key' => 'human-resources.work-sites.create',
        'name' => 'human_resources::app.acl.create',
        'route' => ['admin.human_resources.work_sites.create', 'admin.human_resources.work_sites.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.work-sites.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.work_sites.edit', 'admin.human_resources.work_sites.update'],
        'sort' => 2,
    ], [
        'key' => 'human-resources.work-sites.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.work_sites.delete',
        'sort' => 3,
    ], [
        'key' => 'human-resources.shifts', 'name' => 'human_resources::app.acl.shifts', 'route' => 'admin.human_resources.shifts.index', 'sort' => 6,
    ], [
        'key' => 'human-resources.shifts.create', 'name' => 'human_resources::app.acl.create', 'route' => ['admin.human_resources.shifts.create', 'admin.human_resources.shifts.store'], 'sort' => 1,
    ], [
        'key' => 'human-resources.shifts.edit', 'name' => 'human_resources::app.acl.edit', 'route' => ['admin.human_resources.shifts.edit', 'admin.human_resources.shifts.update'], 'sort' => 2,
    ], [
        'key' => 'human-resources.shifts.delete', 'name' => 'human_resources::app.acl.delete', 'route' => 'admin.human_resources.shifts.delete', 'sort' => 3,
    ], [
        'key' => 'human-resources.departments',
        'name' => 'human_resources::app.acl.departments',
        'route' => 'admin.human_resources.departments.index',
        'sort' => 2,
    ], [
        'key' => 'human-resources.departments.create',
        'name' => 'human_resources::app.acl.create',
        'route' => ['admin.human_resources.departments.create', 'admin.human_resources.departments.store'],
        'sort' => 1,
    ], [
        'key' => 'human-resources.departments.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.departments.edit', 'admin.human_resources.departments.update'],
        'sort' => 2,
    ], [
        'key' => 'human-resources.departments.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.departments.delete',
        'sort' => 3,
    ],
];
