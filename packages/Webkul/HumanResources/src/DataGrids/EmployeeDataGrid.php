<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class EmployeeDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_employees')
            ->leftJoin('hr_departments', 'hr_employees.department_id', '=', 'hr_departments.id')
            ->whereNull('hr_employees.deleted_at')
            ->select(
                'hr_employees.id',
                'hr_employees.employee_number',
                'hr_employees.full_name',
                'hr_employees.job_title',
                'hr_employees.status',
                'hr_departments.name as department_name',
            );

        $this->addFilter('id', 'hr_employees.id');
        $this->addFilter('employee_number', 'hr_employees.employee_number');
        $this->addFilter('full_name', 'hr_employees.full_name');
        $this->addFilter('job_title', 'hr_employees.job_title');
        $this->addFilter('status', 'hr_employees.status');
        $this->addFilter('department_name', 'hr_departments.name');

        return $query;
    }

    public function prepareColumns(): void
    {
        foreach ([
            ['employee_number', 'employee_number'],
            ['full_name', 'full_name'],
            ['department_name', 'department'],
            ['job_title', 'job_title'],
        ] as [$index, $label]) {
            $this->addColumn([
                'index' => $index,
                'label' => trans("human_resources::app.employees.fields.{$label}"),
                'type' => 'string',
                'searchable' => true,
                'filterable' => true,
                'sortable' => true,
                'closure' => fn ($row) => $row->{$index} ?: '—',
            ]);
        }

        $this->addColumn([
            'index' => 'status',
            'label' => trans('human_resources::app.common.status'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
            'closure' => fn ($row) => trans("human_resources::app.employees.statuses.{$row->status}"),
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.employees.view')) {
            $this->addAction([
                'index' => 'view',
                'icon' => 'icon-eye',
                'title' => trans('human_resources::app.acl.view'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.human_resources.employees.show', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('human-resources.employees.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('human_resources::app.common.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.human_resources.employees.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('human-resources.employees.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('human_resources::app.common.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.human_resources.employees.delete', $row->id),
            ]);
        }
    }
}
