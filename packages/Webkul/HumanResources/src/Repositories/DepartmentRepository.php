<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\Department;

class DepartmentRepository extends Repository
{
    protected $fieldSearchable = ['name', 'code'];

    public function model(): string
    {
        return Department::class;
    }
}
