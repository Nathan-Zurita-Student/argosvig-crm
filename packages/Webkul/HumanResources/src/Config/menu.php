<?php

return [
    [
        'key' => 'human-resources',
        'name' => 'human_resources::app.menu.title',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 8,
        'icon-class' => 'icon-user',
    ], [
        'key' => 'human-resources.employees',
        'name' => 'human_resources::app.menu.employees',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 1,
        'icon-class' => '',
    ], [
        'key' => 'human-resources.contracts',
        'name' => 'human_resources::app.menu.contracts',
        'route' => 'admin.human_resources.contracts.index',
        'sort' => 3,
        'icon-class' => '',
    ], [
        'key' => 'human-resources.departments',
        'name' => 'human_resources::app.menu.departments',
        'route' => 'admin.human_resources.departments.index',
        'sort' => 2,
        'icon-class' => '',
    ],
];
