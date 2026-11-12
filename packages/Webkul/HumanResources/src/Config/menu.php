<?php

return [
    [
        'key' => 'workforce',
        'name' => 'human_resources::app.menu.employees',
        'route' => 'admin.human_resources.employees.index',
        'sort' => 8,
        'icon-class' => 'icon-user',
    ], [
        'key' => 'service-contracts',
        'name' => 'human_resources::app.menu.contracts',
        'route' => 'admin.human_resources.contracts.index',
        'sort' => 9,
        'icon-class' => 'icon-quote',
    ], [
        'key' => 'departments',
        'name' => 'human_resources::app.menu.departments',
        'route' => 'admin.human_resources.departments.index',
        'sort' => 10,
        'icon-class' => 'icon-contact',
    ],
];
