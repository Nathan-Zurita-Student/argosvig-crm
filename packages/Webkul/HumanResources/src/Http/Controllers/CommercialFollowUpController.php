<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Models\QuoteVersionProxy;
use Webkul\Lead\Models\LeadProxy;

class CommercialFollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $tabs = array_filter([
            'leads' => bouncer()->hasPermission('leads') && bouncer()->hasPermission('leads.view'),
            'activities' => bouncer()->hasPermission('activities'),
            'quotes' => bouncer()->hasPermission('quotes'),
        ]);
        $tab = $request->route('tab');
        abort_unless(isset($tabs[$tab]), 401);

        $now = now();
        $userIds = bouncer()->getAuthorizedUserIds();
        $records = match ($tab) {
            'leads' => bouncer()->scopeToAuthorizedUsers(LeadProxy::query())
                ->with(['user:id,name', 'stage:id,name'])
                ->whereNull('closed_at')
                ->whereDoesntHave('stage', fn (Builder $stage) => $stage->whereIn('code', ['won', 'lost']))
                ->whereDoesntHave('activities', fn (Builder $activity) => $activity->where('is_done', false)
                    ->where('schedule_to', '>=', $now))
                ->orderBy('created_at')->orderBy('id'),
            'activities' => ActivityProxy::query()->where('is_done', false)->where('schedule_to', '<', $now)
                ->when($userIds !== null, fn (Builder $query) => $query->where(fn (Builder $visible) => $visible
                    ->whereIn('activities.user_id', $userIds)
                    ->orWhereHas('participants', fn (Builder $participant) => $participant->whereIn('user_id', $userIds))))
                ->orderBy('schedule_to')->orderBy('id'),
            'quotes' => QuoteVersionProxy::query()->with('quote.user')->where('status', 'sent')
                ->whereHas('quote', fn (Builder $quote) => bouncer()->scopeToAuthorizedUsers($quote))
                ->orderBy('sent_at')->orderBy('id'),
        };

        return view('human_resources::commercial.index', [
            'records' => $records->paginate(25)->withQueryString(),
            'tabs' => array_keys($tabs),
            'tab' => $tab,
            'detailRoute' => match ($tab) {
                'leads' => 'admin.leads.view',
                'activities' => bouncer()->hasPermission('activities.edit') ? 'admin.activities.edit' : null,
                'quotes' => bouncer()->hasPermission('quotes.edit') ? 'admin.quotes.follow_up.show' : null,
            },
        ]);
    }
}
