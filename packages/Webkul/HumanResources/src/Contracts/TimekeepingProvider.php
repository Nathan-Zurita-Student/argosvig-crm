<?php

namespace Webkul\HumanResources\Contracts;

use Carbon\CarbonImmutable;

interface TimekeepingProvider
{
    /**
     * Yield mark records from an inclusive date range. IDs, when available,
     * must be stable and scoped to the provider account.
     *
     * @return iterable<array{employee_code: string, occurred_at: string, external_id?: string|null}>
     */
    public function entries(CarbonImmutable $from, CarbonImmutable $to): iterable;
}
