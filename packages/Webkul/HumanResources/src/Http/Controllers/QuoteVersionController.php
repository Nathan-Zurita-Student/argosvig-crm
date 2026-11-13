<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Models\QuoteVersionProxy;
use Webkul\Quote\Models\QuoteProxy;

class QuoteVersionController extends Controller
{
    public function index(int $id): View
    {
        $this->authorizeFollowUp();

        $quote = QuoteProxy::query()->findOrFail($id);
        $this->preventUnauthorizedAccess($quote->user_id);

        return view('human_resources::commercial.quote', [
            'quote' => $quote,
            'versions' => QuoteVersionProxy::query()->where('quote_id', $id)->orderByDesc('number')->paginate(10),
            'current' => QuoteVersionProxy::query()->with('contract')->where('quote_id', $id)->orderByDesc('number')->first(),
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $this->authorizeFollowUp();
        $data = $request->validate([
            'status' => ['required', Rule::in(['sent', 'accepted', 'declined'])],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $id) {
            $quote = QuoteProxy::query()->lockForUpdate()->findOrFail($id);
            $this->preventUnauthorizedAccess($quote->user_id);
            $latest = QuoteVersionProxy::query()->where('quote_id', $id)->orderByDesc('number')->lockForUpdate()->first();
            $actor = auth()->guard('user')->id();

            if (($latest?->number ?? 0) !== (int) $data['version'] || $latest?->status === 'accepted') {
                throw ValidationException::withMessages(['status' => trans('human_resources::commercial.quote.changed')]);
            }

            if ($data['status'] === 'sent') {
                $snapshot = $quote->only($quote->getFillable());
                $snapshot['organization_id'] = $quote->person?->organization_id;
                $snapshot['items'] = $quote->items()->orderBy('id')->lockForUpdate()->get()->map(fn ($item) => $item->only($item->getFillable()))->all();

                if ($latest?->status === 'sent') {
                    $latest->update(['status' => 'superseded', 'resolved_at' => now(), 'resolved_by' => $actor]);
                }

                QuoteVersionProxy::query()->create([
                    'quote_id' => $quote->id,
                    'number' => ($latest?->number ?? 0) + 1,
                    'status' => 'sent',
                    'snapshot' => $snapshot,
                    'sent_at' => now(),
                    'sent_by' => $actor,
                ]);

                return;
            }

            if ($latest?->status !== 'sent') {
                throw ValidationException::withMessages(['status' => trans('human_resources::commercial.quote.changed')]);
            }

            $latest->update(['status' => $data['status'], 'resolved_at' => now(), 'resolved_by' => $actor]);
        });

        return redirect()->route('admin.quotes.follow_up.show', $id)->with('success', trans('human_resources::workforce.saved'));
    }

    private function authorizeFollowUp(): void
    {
        abort_unless(bouncer()->hasPermission('quotes') && bouncer()->hasPermission('quotes.edit'), 401);
    }
}
