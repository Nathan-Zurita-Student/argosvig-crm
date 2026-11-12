<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\Employee;

class EmployeeRepository extends Repository
{
    protected $fieldSearchable = ['employee_number', 'full_name', 'cpf', 'email', 'alterdata_employee_id'];

    public function model(): string
    {
        return Employee::class;
    }
}
