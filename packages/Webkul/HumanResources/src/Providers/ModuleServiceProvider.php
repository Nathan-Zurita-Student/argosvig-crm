<?php

namespace Webkul\HumanResources\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\HumanResources\Models\Department;
use Webkul\HumanResources\Models\Employee;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Department::class,
        Employee::class,
    ];
}
