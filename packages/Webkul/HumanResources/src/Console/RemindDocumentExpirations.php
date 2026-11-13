<?php

namespace Webkul\HumanResources\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Models\NotificationProxy;
use Webkul\Admin\Repositories\NotificationRepository;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;
use Webkul\User\Models\UserProxy;

class RemindDocumentExpirations extends Command
{
    protected $signature = 'hr:remind-document-expirations';

    protected $description = 'Notify authorized users about documents expiring within 30 days';

    public function handle(NotificationRepository $notifications): int
    {
        $date = today();
        $due = EmployeeDocumentProxy::query()->whereBetween('expires_at', [$date->toDateString(), $date->copy()->addDays(30)->toDateString()]);
        $employee = (clone $due)->whereHas('employee', fn ($query) => $query->where('status', 'active'))->exists();
        $shared = (clone $due)->whereNull('employee_id')->exists();

        if (! $employee && ! $shared) {
            return self::SUCCESS;
        }

        UserProxy::query()->where('status', true)->where('view_permission', 'global')->chunkById(100, function ($users) use ($employee, $shared, $date, $notifications) {
            foreach ($users as $candidate) {
                DB::transaction(function () use ($candidate, $employee, $shared, $date, $notifications) {
                    $user = UserProxy::query()->whereKey($candidate->id)->where('status', true)->where('view_permission', 'global')->lockForUpdate()->first();

                    if (! $user?->role) {
                        return;
                    }

                    $allows = fn (string $key) => $user->role->permission_type === 'all' || $user->hasPermission($key);
                    $privateDue = $employee && $allows('human-resources.document-center');
                    $sharedDue = $shared && $allows('human-resources.shared-documents');

                    if (! $allows('document-center') || (! $privateDue && ! $sharedDue)) {
                        return;
                    }

                    $alreadySent = NotificationProxy::query()->where('user_id', $user->id)
                        ->where('entity_type', 'hr_document')->where('action', 'expiry_reminder')
                        ->where('created_at', '>=', $date)->exists();

                    if (! $alreadySent) {
                        $filters = ['expiry' => 'soon'];

                        if ($privateDue !== $sharedDue) {
                            $filters['category'] = $privateDue ? 'employee' : 'shared';
                        }

                        $notifications->create([
                            'user_id' => $user->id,
                            'entity_type' => 'hr_document',
                            'action' => 'expiry_reminder',
                            'title' => trans('human_resources::readiness.document_reminder'),
                            'route' => route('admin.human_resources.document_center.index', $filters, false),
                        ]);
                    }
                });
            }
        });

        return self::SUCCESS;
    }
}
