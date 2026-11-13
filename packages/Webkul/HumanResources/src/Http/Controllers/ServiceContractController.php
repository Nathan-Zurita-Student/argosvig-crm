<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\HumanResources\DataGrids\ServiceContractDataGrid;
use Webkul\HumanResources\Http\Requests\ServiceContractRequest;
use Webkul\HumanResources\Models\QuoteVersion;
use Webkul\HumanResources\Models\QuoteVersionProxy;
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
        $id = $request->validate(['quote_version_id' => ['nullable', 'integer', 'min:1']])['quote_version_id'] ?? null;
        $version = $id ? $this->acceptedVersion((int) $id) : null;

        $contract = $version ? $this->repository->makeModel()->fill([
            'title' => $version->snapshot['subject'],
            'organization_id' => $version->snapshot['organization_id'],
            'status' => 'draft',
        ]) : null;

        return view('human_resources::contracts.create', [...$this->formData(), 'quoteVersion' => $version, 'contract' => $contract]);
    }

    public function store(ServiceContractRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $id = $data['quote_version_id'] ?? null;
        unset($data['quote_version_id']);

        if ($id) {
            DB::transaction(function () use ($data, $id) {
                $version = $this->acceptedVersion((int) $id, true);

                if ($version->snapshot['organization_id'] && (int) $data['organization_id'] !== (int) $version->snapshot['organization_id']) {
                    throw ValidationException::withMessages(['organization_id' => trans('human_resources::commercial.quote.organization_mismatch')]);
                }

                $version->contract()->create(['status' => 'draft'] + $data);
            });
        } else {
            $this->repository->create($data);
        }
        session()->flash('success', trans('human_resources::app.contracts.messages.created'));

        return redirect()->route('admin.human_resources.contracts.index');
    }

    public function edit(int $id): View
    {
        return view('human_resources::contracts.edit', [...$this->formData(), 'contract' => $this->repository->findOrFail($id)]);
    }

    public function update(ServiceContractRequest $request, int $id): RedirectResponse
    {
        $this->repository->update($request->validated(), $id);
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
}
