<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\HumanResources\DataGrids\ServiceContractDataGrid;
use Webkul\HumanResources\Http\Requests\ServiceContractRequest;
use Webkul\HumanResources\Models\QuoteVersion;
use Webkul\HumanResources\Models\QuoteVersionProxy;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Models\ServiceContractProxy;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\User\Repositories\UserRepository;

class ServiceContractController extends Controller
{
    public function __construct(protected ServiceContractRepository $repository, protected OrganizationRepository $organizationRepository, protected UserRepository $userRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(ServiceContractDataGrid::class)->process() : view('human_resources::contracts.index');
    }

    public function create(Request $request): View
    {
        $source = $request->validate([
            'quote_version_id' => ['nullable', 'prohibits:renews_contract_id', 'integer', 'min:1'],
            'renews_contract_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $version = isset($source['quote_version_id']) ? $this->acceptedVersion((int) $source['quote_version_id']) : null;
        $previous = isset($source['renews_contract_id']) ? $this->renewableContract((int) $source['renews_contract_id']) : null;

        $contract = $version ? $this->repository->makeModel()->fill([
            'title' => $version->snapshot['subject'],
            'organization_id' => $version->snapshot['organization_id'],
            'status' => 'draft',
        ]) : null;

        if ($previous) {
            $contract = $this->repository->makeModel()->fill($previous->only(['title', 'organization_id', 'manager_id', 'monthly_value', 'description']));
        }

        return view('human_resources::contracts.create', [...$this->formData(), 'quoteVersion' => $version, 'previous' => $previous, 'contract' => $contract]);
    }

    public function store(ServiceContractRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $id = $data['quote_version_id'] ?? null;
        $previous = $data['renews_contract_id'] ?? null;
        unset($data['quote_version_id'], $data['renews_contract_id']);

        if ($id) {
            DB::transaction(function () use ($data, $id) {
                $version = $this->acceptedVersion((int) $id, true);

                if ($version->snapshot['organization_id'] && (int) $data['organization_id'] !== (int) $version->snapshot['organization_id']) {
                    throw ValidationException::withMessages(['organization_id' => trans('human_resources::commercial.quote.organization_mismatch')]);
                }

                $version->contract()->create(['status' => 'draft'] + $data);
            });
        } elseif ($previous) {
            DB::transaction(function () use ($data, $previous) {
                $contract = $this->renewableContract((int) $previous, true);

                if ((int) $data['organization_id'] !== (int) $contract->organization_id) {
                    throw ValidationException::withMessages(['organization_id' => trans('human_resources::readiness.same_renewal_company')]);
                }

                if ($contract->ends_at->gte(Carbon::parse($data['starts_at'])->startOfDay())) {
                    throw ValidationException::withMessages(['starts_at' => trans('human_resources::readiness.renewal_start')]);
                }

                $contract->renewal()->create(['status' => 'draft'] + $data);
            });
        } else {
            $this->repository->create($data);
        }
        session()->flash('success', trans('human_resources::app.contracts.messages.created'));

        return redirect()->route('admin.human_resources.contracts.index');
    }

    public function edit(int $id): View
    {
        $contract = $this->repository->findOrFail($id);

        return view('human_resources::contracts.edit', [...$this->formData(), 'contract' => $contract, 'canRenew' => $this->canRenew($contract)]);
    }

    public function update(ServiceContractRequest $request, int $id): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $id) {
            $contract = ServiceContractProxy::query()->lockForUpdate()->findOrFail($id);
            $previous = $contract->previousContract;
            $renewal = $contract->renewal;

            if (($previous || $renewal || $contract->quote_version_id) && (int) $data['organization_id'] !== (int) $contract->organization_id) {
                throw ValidationException::withMessages(['organization_id' => trans('human_resources::readiness.linked_company')]);
            }

            if ($previous?->ends_at?->gte(Carbon::parse($data['starts_at'])->startOfDay())) {
                throw ValidationException::withMessages(['starts_at' => trans('human_resources::readiness.renewal_start')]);
            }

            if ($renewal && (empty($data['ends_at']) || $renewal->starts_at->lte(Carbon::parse($data['ends_at'])->startOfDay()))) {
                throw ValidationException::withMessages(['ends_at' => trans('human_resources::readiness.renewal_end')]);
            }

            $this->repository->update($data, $id);
        }, 3);
        session()->flash('success', trans('human_resources::app.contracts.messages.updated'));

        return redirect()->route('admin.human_resources.contracts.index');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => trans('human_resources::app.contracts.messages.deleted')]);
    }

    private function formData(): array
    {
        return ['organizations' => $this->organizationRepository->orderBy('name')->all(), 'users' => $this->userRepository->orderBy('name')->all()];
    }

    private function acceptedVersion(int $id, bool $lock = false): QuoteVersion
    {
        abort_unless(bouncer()->hasPermission('human-resources.contracts.create') && bouncer()->hasPermission('quotes') && bouncer()->hasPermission('quotes.edit'), 401);

        $version = QuoteVersionProxy::query()->where('status', 'accepted')
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
        $quote = $version->quote;
        abort_unless($quote, 404);
        $this->preventUnauthorizedAccess($quote->user_id);

        if ($version->contract()->exists()) {
            throw ValidationException::withMessages(['quote_version_id' => trans('human_resources::commercial.quote.contract_exists')]);
        }

        return $version;
    }

    private function canRenew(ServiceContract $contract): bool
    {
        return bouncer()->hasPermission('human-resources.contracts.create')
            && bouncer()->hasPermission('human-resources.contracts.edit')
            && in_array($contract->status, ['active', 'expired'], true)
            && $contract->ends_at !== null
            && ! $contract->renewal()->exists();
    }

    private function renewableContract(int $id, bool $lock = false): ServiceContract
    {
        abort_unless(bouncer()->hasPermission('human-resources.contracts.create') && bouncer()->hasPermission('human-resources.contracts.edit'), 401);

        $contract = ServiceContractProxy::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);

        if (! $this->canRenew($contract)) {
            throw ValidationException::withMessages(['renews_contract_id' => trans('human_resources::readiness.renewal_unavailable')]);
        }

        return $contract;
    }
}
