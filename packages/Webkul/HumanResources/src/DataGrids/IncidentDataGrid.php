<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class IncidentDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_incidents')->leftJoin('hr_employees', 'hr_incidents.employee_id', '=', 'hr_employees.id')->whereNull('hr_incidents.deleted_at')->select('hr_incidents.id', 'hr_incidents.code', 'hr_incidents.title', 'hr_incidents.occurred_at', 'hr_incidents.severity', 'hr_incidents.status', 'hr_employees.full_name as employee_name');
        foreach (['code', 'title', 'severity', 'status', 'occurred_at'] as $field) {
            $this->addFilter($field, "hr_incidents.{$field}");
        }
        $this->addFilter('employee_name', 'hr_employees.full_name');

        return $query;
    }

    public function prepareColumns(): void
    {
        foreach (['code', 'title', 'employee_name', 'occurred_at'] as $field) {
            $this->addColumn(['index' => $field, 'label' => trans("human_resources::app.incidents.fields.{$field}"), 'type' => 'string', 'searchable' => true, 'filterable' => true, 'sortable' => true, 'closure' => fn ($row) => $row->{$field} ?: '—']);
        }
        foreach (['severity', 'status'] as $field) {
            $this->addColumn(['index' => $field, 'label' => trans("human_resources::app.incidents.fields.{$field}"), 'type' => 'string', 'filterable' => true, 'closure' => fn ($row) => trans("human_resources::app.incidents.{$field}.{$row->{$field}}")]);
        }
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.incidents.view')) {
            $this->addAction(['index' => 'view', 'icon' => 'icon-eye', 'title' => trans('human_resources::app.acl.view'), 'method' => 'GET', 'url' => fn ($row) => route('admin.human_resources.incidents.show', $row->id)]);
        }
        if (bouncer()->hasPermission('human-resources.incidents.edit')) {
            $this->addAction(['index' => 'edit', 'icon' => 'icon-edit', 'title' => trans('human_resources::app.common.edit'), 'method' => 'GET', 'url' => fn ($row) => route('admin.human_resources.incidents.edit', $row->id)]);
        }
        if (bouncer()->hasPermission('human-resources.incidents.delete')) {
            $this->addAction(['index' => 'delete', 'icon' => 'icon-delete', 'title' => trans('human_resources::app.common.delete'), 'method' => 'DELETE', 'url' => fn ($row) => route('admin.human_resources.incidents.delete', $row->id)]);
        }
    }
}
