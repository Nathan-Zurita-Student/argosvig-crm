<?php

namespace Webkul\HumanResources\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ServiceContractDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $query = DB::table('hr_service_contracts')->join('organizations', 'hr_service_contracts.organization_id', '=', 'organizations.id')->whereNull('hr_service_contracts.deleted_at')->select('hr_service_contracts.id', 'hr_service_contracts.contract_number', 'hr_service_contracts.title', 'hr_service_contracts.monthly_value', 'hr_service_contracts.status', 'hr_service_contracts.ends_at', 'organizations.name as organization_name');
        foreach (['contract_number', 'title', 'status', 'ends_at'] as $field) {
            $this->addFilter($field, "hr_service_contracts.{$field}");
        }
        $this->addFilter('organization_name', 'organizations.name');

        return $query;
    }

    public function prepareColumns(): void
    {
        foreach (['contract_number', 'title', 'organization_name', 'ends_at'] as $field) {
            $this->addColumn(['index' => $field, 'label' => trans("human_resources::app.contracts.fields.{$field}"), 'type' => 'string', 'searchable' => true, 'filterable' => true, 'sortable' => true]);
        }
        $this->addColumn([
            'index' => 'monthly_value',
            'label' => trans('human_resources::app.contracts.fields.monthly_value'),
            'type' => 'float',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatBasePrice($row->monthly_value),
        ]);
        $this->addColumn(['index' => 'status', 'label' => trans('human_resources::app.common.status'), 'type' => 'string', 'filterable' => true, 'closure' => fn ($row) => trans("human_resources::app.contracts.statuses.{$row->status}")]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('human-resources.contracts.edit')) {
            $this->addAction(['index' => 'edit', 'icon' => 'icon-edit', 'title' => trans('human_resources::app.common.edit'), 'method' => 'GET', 'url' => fn ($row) => route('admin.human_resources.contracts.edit', $row->id)]);
        }
        if (bouncer()->hasPermission('human-resources.contracts.delete')) {
            $this->addAction(['index' => 'delete', 'icon' => 'icon-delete', 'title' => trans('human_resources::app.common.delete'), 'method' => 'DELETE', 'url' => fn ($row) => route('admin.human_resources.contracts.delete', $row->id)]);
        }
    }
}
