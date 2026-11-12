<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\ServiceContract;

class ServiceContractRepository extends Repository
{
    public function model(): string
    {
        return ServiceContract::class;
    }
}
