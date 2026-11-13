<?php

namespace Webkul\HumanResources\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Models\NotificationProxy;
use Webkul\Admin\Repositories\NotificationRepository;
use Webkul\HumanResources\Models\ShiftAssignmentProxy;
use Webkul\User\Models\UserProxy;

class NotifyOperationsHandover extends Command
{
    protected $signature = 'hr:notify-operations-handover';

    protected $description = 'Notify operations staff about a handover when there are upcoming confirmed or planned shifts';

    public function handle(NotificationRepository $notifications): int
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $nextDay = $today->copy()->addDay();
        $hasShifts = ShiftAssignmentProxy::query()->whereHas('workSite', fn ($query) => $query->where('status', 'active'))
            ->whereIn('status', ['planned', 'confirmed'])->where('ends_at', '>', $now)->where('starts_at', '<', $nextDay)->exists();

        if (! $hasShifts) {
            return self::SUCCESS;
        }

        UserProxy::query()->where('status', true)->where('view_permission', 'global')->chunkById(100, function ($users) use ($notifications, $today) {
            foreach ($users as $candidate) {
                DB::transaction(function () use ($candidate, $notifications, $today) {
                    $user = UserProxy::query()->whereKey($candidate->id)->where('status', true)->where('view_permission', 'global')->lockForUpdate()->first();

                    if (! $user?->role || ($user->role->permission_type !== 'all' && collect(['operations-assistant', 'human-resources.work-sites', 'human-resources.shifts'])->contains(fn ($key) => ! $user->hasPermission($key)))) {
                        return;
                    }

                    $sent = NotificationProxy::query()->where('user_id', $user->id)->where('entity_type', 'hr_operations')
                        ->where('action', 'handover')->where('created_at', '>=', $today)->exists();

                    if (! $sent) {
                        $notifications->create([
                            'user_id' => $user->id, 'entity_type' => 'hr_operations', 'action' => 'handover',
                            'title' => trans('human_resources::operations.handover_notification'),
                            'route' => route('admin.operations.index', ['from' => $today->toDateString()], false),
                        ]);
                    }
                });
            }
        });

        return self::SUCCESS;
    }
}
