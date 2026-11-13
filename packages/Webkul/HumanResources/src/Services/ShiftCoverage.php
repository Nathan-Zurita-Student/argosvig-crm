<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Repositories\ShiftAssignmentRepository;

class ShiftCoverage
{
    public function __construct(private ShiftAssignmentRepository $shifts, private EmployeeRepository $employees) {}

    public static function allowed(): bool
    {
        return bouncer()->hasPermission('human-resources.work-sites') && bouncer()->hasPermission('human-resources.shifts');
    }

    public function gaps(Collection $sites, Carbon $from, Carbon $to): array
    {
        if ($from->gte($to)) {
            return [];
        }

        $shifts = $this->shifts->getModel()->newQuery()->whereIn('work_site_id', $sites->pluck('id'))
            ->whereIn('status', ['planned', 'confirmed', 'absence'])
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get(['work_site_id', 'employee_id', 'starts_at', 'ends_at', 'status'])->groupBy('work_site_id');
        $confirmedEmployees = $this->employees->getModel()->newQuery()->where('status', 'active')
            ->whereIn('id', $shifts->flatten(1)->where('status', 'confirmed')->pluck('employee_id')->unique())->pluck('id')->flip();

        $gaps = [];

        foreach ($sites as $site) {
            $events = [];

            foreach ($shifts->get($site->id, []) as $shift) {
                $start = max($from->timestamp, $shift->starts_at->timestamp);
                $end = min($to->timestamp, $shift->ends_at->timestamp);
                $employee = $shift->status === 'confirmed' && $confirmedEmployees->has($shift->employee_id) ? $shift->employee_id : 0;
                $events[$start][] = [$employee, 1];
                $events[$end][] = [$employee, -1];
            }

            ksort($events);
            $active = [];
            $previous = null;

            foreach ($events as $instant => $changes) {
                $confirmed = count($active) - (int) isset($active[0]);
                if ($active && $confirmed < $site->required_staff) {
                    $gaps[] = [
                        'site' => $site, 'from' => Carbon::createFromTimestamp($previous, $from->getTimezone()),
                        'to' => Carbon::createFromTimestamp($instant, $from->getTimezone()),
                        'confirmed' => $confirmed, 'required' => $site->required_staff,
                    ];
                }

                foreach ($changes as [$employee, $delta]) {
                    $active[$employee] = ($active[$employee] ?? 0) + $delta;
                    if ($active[$employee] === 0) {
                        unset($active[$employee]);
                    }
                }
                $previous = $instant;
            }
        }

        usort($gaps, fn ($left, $right) => [$left['from']->timestamp, $left['site']->id] <=> [$right['from']->timestamp, $right['site']->id]);

        return $gaps;
    }

    public static function canSuggest(): bool
    {
        return self::allowed() && bouncer()->hasPermission('human-resources.employees') && bouncer()->hasPermission('human-resources.vacations');
    }

    public function candidates(Carbon $from, Carbon $to): Collection
    {
        $busy = $this->shifts->getModel()->newQuery()->whereIn('status', ['planned', 'confirmed', 'completed', 'absence'])
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->select('employee_id');

        return $this->employees->getModel()->newQuery()->where('status', 'active')->whereNotIn('id', $busy)
            ->whereDoesntHave('vacations', fn ($vacation) => $vacation->whereIn('status', ['scheduled', 'in_progress'])
                ->where('starts_at', '<=', $to->copy()->subSecond()->toDateString())->where('ends_at', '>=', $from->toDateString()))
            ->orderBy('full_name')->orderBy('id')->limit(10)->get(['id', 'full_name', 'job_title']);
    }
}
