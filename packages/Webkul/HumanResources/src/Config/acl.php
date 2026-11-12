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
        'key' => 'human-resources.employees.edit',
        'name' => 'human_resources::app.acl.edit',
        'route' => ['admin.human_resources.employees.edit', 'admin.human_resources.employees.update'],
        'sort' => 2,
    ], [
        'key' => 'human-resources.employees.delete',
        'name' => 'human_resources::app.acl.delete',
        'route' => 'admin.human_resources.employees.delete',
        'sort' => 3,
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
