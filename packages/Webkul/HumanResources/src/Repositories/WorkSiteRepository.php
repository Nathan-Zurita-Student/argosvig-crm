<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\WorkSite;

class WorkSiteRepository extends Repository
{
    public function model(): string
    {
        return WorkSite::class;
    }
}
