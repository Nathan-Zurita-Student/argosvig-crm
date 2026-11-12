<?php

return [
    'menu' => [
        'title' => 'People',
        'employees' => 'Employees',
        'departments' => 'Departments',
    ],
    'acl' => [
        'title' => 'People',
        'employees' => 'Employees',
        'departments' => 'Departments',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],
    'common' => [
        'active' => 'Active',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'inactive' => 'Inactive',
        'save' => 'Save',
        'select' => 'Select',
        'status' => 'Status',
    ],
    'departments' => [
        'index' => [
            'title' => 'Departments',
            'description' => 'Organize employees by operating area.',
            'create' => 'Create department',
        ],
        'create' => ['title' => 'Create department'],
        'edit' => ['title' => 'Edit department'],
        'fields' => [
            'name' => 'Name',
            'code' => 'Code',
            'description' => 'Description',
            'employees_count' => 'Employees',
            'is_active' => 'Active department',
        ],
        'messages' => [
            'created' => 'Department created successfully.',
            'updated' => 'Department updated successfully.',
            'deleted' => 'Department deleted successfully.',
            'in-use' => 'This department cannot be deleted while it has employees.',
        ],
    ],
    'employees' => [
        'index' => [
            'title' => 'Employees',
            'description' => 'Manage the Argosvig workforce and its integration identifiers.',
            'create' => 'Create employee',
        ],
        'create' => ['title' => 'Create employee'],
        'edit' => ['title' => 'Edit employee'],
        'fields' => [
            'employee_number' => 'Employee number',
            'full_name' => 'Full name',
            'cpf' => 'CPF',
            'email' => 'Email',
            'phone' => 'Phone',
            'job_title' => 'Job title',
            'hire_date' => 'Hire date',
            'department' => 'Department',
            'alterdata_employee_id' => 'Alterdata ID',
        ],
        'statuses' => [
            'active' => 'Active',
            'on_leave' => 'On leave',
            'inactive' => 'Inactive',
        ],
        'messages' => [
            'created' => 'Employee created successfully.',
            'updated' => 'Employee updated successfully.',
            'deleted' => 'Employee deleted successfully.',
        ],
    ],
];
