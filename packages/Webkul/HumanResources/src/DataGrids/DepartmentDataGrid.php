<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class DepartmentDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_departments')
            ->leftJoin('hr_employees', function ($join) {
                $join->on('hr_departments.id', '=', 'hr_employees.department_id')
                    ->whereNull('hr_employees.deleted_at');
            })
            ->select(
                'hr_departments.id',
                'hr_departments.name',
                'hr_departments.code',
                'hr_departments.is_active',
                DB::raw('COUNT(hr_employees.id) as employees_count'),
            )
            ->groupBy('hr_departments.id');

        $this->addFilter('id', 'hr_departments.id');
        $this->addFilter('name', 'hr_departments.name');
        $this->addFilter('code', 'hr_departments.code');
        $this->addFilter('is_active', 'hr_departments.is_active');

        return $query;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'name',
            'label' => trans('human_resources::app.departments.fields.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'code',
            'label' => trans('human_resources::app.departments.fields.code'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'closure' => fn ($row) => $row->code ?: '—',
        ]);

        $this->addColumn([
            'index' => 'employees_count',
            'label' => trans('human_resources::app.departments.fields.employees_count'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'is_active',
            'label' => trans('human_resources::app.common.status'),
            'type' => 'boolean',
            'filterable' => true,
            'sortable' => true,
            'closure' => fn ($row) => trans($row->is_active ? 'human_resources::app.common.active' : 'human_resources::app.common.inactive'),
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.departments.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('human_resources::app.common.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.human_resources.departments.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('human-resources.departments.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('human_resources::app.common.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.human_resources.departments.delete', $row->id),
            ]);
        }
    }
}
