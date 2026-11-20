<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\Checklist;

class ChecklistRepository extends Repository
{
    public function model(): string
    {
        return Checklist::class;
    }
}
