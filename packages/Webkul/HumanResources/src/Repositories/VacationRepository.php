<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\Vacation;

class VacationRepository extends Repository
{
    public function model(): string
    {
        return Vacation::class;
    }
}
