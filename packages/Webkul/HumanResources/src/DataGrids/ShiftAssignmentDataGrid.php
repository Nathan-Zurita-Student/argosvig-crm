<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ShiftAssignmentDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_shift_assignments')->join('hr_work_sites', 'hr_shift_assignments.work_site_id', '=', 'hr_work_sites.id')->join('hr_employees', 'hr_shift_assignments.employee_id', '=', 'hr_employees.id')->select('hr_shift_assignments.id', 'hr_shift_assignments.starts_at', 'hr_shift_assignments.ends_at', 'hr_shift_assignments.shift_type', 'hr_shift_assignments.status', 'hr_work_sites.name as work_site_name', 'hr_employees.full_name as employee_name');
        foreach (['starts_at', 'ends_at', 'shift_type', 'status'] as $field) {
            $this->addFilter($field, "hr_shift_assignments.{$field}");
        }
        $this->addFilter('work_site_name', 'hr_work_sites.name');
        $this->addFilter('employee_name', 'hr_employees.full_name');

        return $query;
    }

    public function prepareColumns(): void
    {
        foreach (['work_site_name', 'employee_name', 'starts_at', 'ends_at'] as $field) {
            $this->addColumn(['index' => $field, 'label' => trans("human_resources::app.shifts.fields.{$field}"), 'type' => 'string', 'searchable' => true, 'filterable' => true, 'sortable' => true]);
        }
        foreach (['shift_type', 'status'] as $field) {
            $this->addColumn(['index' => $field, 'label' => trans("human_resources::app.shifts.fields.{$field}"), 'type' => 'string', 'filterable' => true, 'closure' => fn ($row) => trans("human_resources::app.shifts.{$field}.{$row->{$field}}")]);
        }
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.shifts.edit')) {
            $this->addAction(['index' => 'edit', 'icon' => 'icon-edit', 'title' => trans('human_resources::app.common.edit'), 'method' => 'GET', 'url' => fn ($row) => route('admin.human_resources.shifts.edit', $row->id)]);
        }
        if (bouncer()->hasPermission('human-resources.shifts.delete')) {
            $this->addAction(['index' => 'delete', 'icon' => 'icon-delete', 'title' => trans('human_resources::app.common.delete'), 'method' => 'DELETE', 'url' => fn ($row) => route('admin.human_resources.shifts.delete', $row->id)]);
        }
    }
}
