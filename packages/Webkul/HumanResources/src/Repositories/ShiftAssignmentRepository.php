<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\ShiftAssignment;

class ShiftAssignmentRepository extends Repository
{
    public function model(): string
    {
        return ShiftAssignment::class;
    }
}
