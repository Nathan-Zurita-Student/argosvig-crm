<?php

namespace Webkul\HumanResources\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\HumanResources\Models\Department;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\EmployeeDocument;
use Webkul\HumanResources\Models\Incident;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Models\WorkSite;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Department::class,
        Employee::class,
        EmployeeDocument::class,
        ServiceContract::class,
        Incident::class,
        WorkSite::class,
    ];
}
