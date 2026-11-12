<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\EmployeeDocument;

class EmployeeDocumentRepository extends Repository
{
    protected $fieldSearchable = ['type', 'name', 'original_name', 'status'];

    public function model(): string
    {
        return EmployeeDocument::class;
    }
}
