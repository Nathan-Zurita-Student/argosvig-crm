<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\Incident;

class IncidentRepository extends Repository
{
    public function model(): string
    {
        return Incident::class;
    }
}
