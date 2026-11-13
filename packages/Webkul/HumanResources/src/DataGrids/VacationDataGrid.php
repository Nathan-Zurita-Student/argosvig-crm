<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class VacationDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_vacations')
            ->join('hr_employees', 'hr_vacations.employee_id', '=', 'hr_employees.id')
            ->leftJoin('hr_departments', 'hr_employees.department_id', '=', 'hr_departments.id')
            ->whereNull('hr_vacations.deleted_at')
            ->select(
                'hr_vacations.id',
                'hr_vacations.employee_id',
                'hr_vacations.starts_at',
                'hr_vacations.ends_at',
                'hr_vacations.days',
                'hr_vacations.type',
                'hr_vacations.status',
                'hr_employees.employee_number',
                'hr_employees.full_name as employee_name',
                'hr_departments.name as department_name',
            );

        foreach (['starts_at', 'ends_at', 'type', 'status'] as $field) {
            $this->addFilter($field, "hr_vacations.{$field}");
        }

        $this->addFilter('employee_number', 'hr_employees.employee_number');
        $this->addFilter('employee_name', 'hr_employees.full_name');
        $this->addFilter('department_name', 'hr_departments.name');

        return $query;
    }

    public function prepareColumns(): void
    {
        foreach ([
            ['employee_number', 'employee_number', 'string'],
            ['employee_name', 'employee_name', 'string'],
            ['department_name', 'department', 'string'],
            ['starts_at', 'starts_at', 'date'],
            ['ends_at', 'ends_at', 'date'],
            ['days', 'days', 'integer'],
        ] as [$index, $label, $type]) {
            $this->addColumn([
                'index' => $index,
                'label' => trans("human_resources::app.vacations.fields.{$label}"),
                'type' => $type,
                'searchable' => $type === 'string',
                'filterable' => true,
                'sortable' => true,
                'closure' => fn ($row) => $index === 'employee_name'
                    ? $this->primaryLink($row->employee_name, route('admin.human_resources.employees.show', $row->employee_id))
                    : ($row->{$index} ?: '—'),
            ]);
        }

        $this->addColumn([
            'index' => 'type',
            'label' => trans('human_resources::app.vacations.fields.type'),
            'type' => 'string',
            'filterable' => true,
            'closure' => fn ($row) => trans("human_resources::app.vacations.types.{$row->type}"),
        ]);
        $this->addColumn([
            'index' => 'status',
            'label' => trans('human_resources::app.common.status'),
            'type' => 'string',
            'filterable' => true,
            'closure' => fn ($row) => trans("human_resources::app.vacations.statuses.{$row->status}"),
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.vacations.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('human_resources::app.common.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.human_resources.vacations.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('human-resources.vacations.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('human_resources::app.common.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.human_resources.vacations.delete', $row->id),
            ]);
        }
    }
}
