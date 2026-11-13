<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class WorkSiteDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_work_sites')->leftJoin('hr_service_contracts', 'hr_work_sites.service_contract_id', '=', 'hr_service_contracts.id')->whereNull('hr_work_sites.deleted_at')->select('hr_work_sites.id', 'hr_work_sites.code', 'hr_work_sites.name', 'hr_work_sites.city', 'hr_work_sites.state', 'hr_work_sites.required_staff', 'hr_work_sites.status', 'hr_service_contracts.contract_number');
        foreach (['code', 'name', 'city', 'state', 'status'] as $field) {
            $this->addFilter($field, "hr_work_sites.{$field}");
        }
        $this->addFilter('contract_number', 'hr_service_contracts.contract_number');

        return $query;
    }

    public function prepareColumns(): void
    {
        foreach (['code', 'name', 'contract_number', 'city', 'required_staff'] as $field) {
            $this->addColumn(['index' => $field, 'label' => trans("human_resources::app.work_sites.fields.{$field}"), 'type' => 'string', 'searchable' => true, 'filterable' => true, 'sortable' => true, 'closure' => fn ($row) => $field === 'name' ? $this->primaryLink($row->name, route('admin.human_resources.work_sites.edit', $row->id)) : ($row->{$field} ?: '—')]);
        }
        $this->addColumn(['index' => 'status', 'label' => trans('human_resources::app.common.status'), 'type' => 'string', 'filterable' => true, 'closure' => fn ($row) => trans("human_resources::app.work_sites.status.{$row->status}")]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.work-sites.edit')) {
            $this->addAction(['index' => 'edit', 'icon' => 'icon-edit', 'title' => trans('human_resources::app.common.edit'), 'method' => 'GET', 'url' => fn ($row) => route('admin.human_resources.work_sites.edit', $row->id)]);
        }
        if (bouncer()->hasPermission('human-resources.work-sites.delete')) {
            $this->addAction(['index' => 'delete', 'icon' => 'icon-delete', 'title' => trans('human_resources::app.common.delete'), 'method' => 'DELETE', 'url' => fn ($row) => route('admin.human_resources.work_sites.delete', $row->id)]);
        }
    }
}
