<?php

namespace Webkul\HumanResources\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\HumanResources\Contracts\ChecklistColumn;

class ChecklistColumnRepository extends Repository
{
    public function model(): string
    {
        return ChecklistColumn::class;
    }
}
