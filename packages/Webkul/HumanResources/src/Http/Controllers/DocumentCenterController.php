<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\HumanResources\Http\Requests\EmployeeDocumentRequest;
use Webkul\HumanResources\Models\EmployeeDocument;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\HumanResources\Services\DocumentLibrary;

class DocumentCenterController extends Controller
{
    public function __construct(private DocumentLibrary $library) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in([...EmployeeDocument::EMPLOYEE_TYPES, ...EmployeeDocument::SHARED_TYPES])],
            'competence' => ['nullable', 'date_format:Y-m'],
            'category' => ['nullable', Rule::in(['employee', 'shared'])],
            'expiry' => ['nullable', Rule::in(['expired', 'soon', 'without_date'])],
        ]);

        $private = $this->can('human-resources.document-center');
        $shared = $this->can('human-resources.shared-documents');
        abort_unless($private || $shared, 401);

        $query = EmployeeDocumentProxy::query()->with([
            'employee' => fn ($query) => $query->withTrashed(),
            'organizationLinks.organization',
            'contracts',
        ]);

        if (! $private || ($filters['category'] ?? null) === 'shared') {
            $query->whereNull('employee_id');
        }

        if (! $shared || ($filters['category'] ?? null) === 'employee') {
            $query->whereNotNull('employee_id');
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)
                ->orWhereHas('employee', fn ($employee) => $employee->withTrashed()->where(fn ($identity) => $identity
                    ->where('full_name', 'like', $search)->orWhere('employee_number', 'like', $search)))
                ->orWhereHas('organizations', fn ($organization) => $organization->where('name', 'like', $search))
                ->orWhereHas('contracts', fn ($contract) => $contract->where('contract_number', 'like', $search)));
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['competence'])) {
            $query->whereDate('competence', $filters['competence'].'-01');
        }

        match ($filters['expiry'] ?? null) {
            'expired' => $query->whereDate('expires_at', '<', today()),
            'soon' => $query->whereBetween('expires_at', [today()->toDateString(), today()->addDays(30)->toDateString()]),
            'without_date' => $query->whereNull('expires_at'),
            default => null,
        };

        return view('human_resources::documents.index', [
            'documents' => $query->latest('id')->paginate(25)->withQueryString(),
            'types' => [...($private ? EmployeeDocument::EMPLOYEE_TYPES : []), ...($shared ? EmployeeDocument::SHARED_TYPES : [])],
            'canViewBoth' => $private && $shared,
            'canUpload' => $this->canManage('human-resources.document-center'),
            'canShare' => $this->canManage('human-resources.shared-documents'),
        ]);
    }

    public function create(): View
    {
        abort_unless($this->canManage('human-resources.document-center'), 401);

        return $this->form();
    }

    public function createShared(): View
    {
        abort_unless($this->canManage('human-resources.shared-documents'), 401);

        return $this->form(shared: true);
    }

    public function store(EmployeeDocumentRequest $request): RedirectResponse
    {
        abort_unless($this->canManage('human-resources.document-center'), 401);
        $employeeId = $request->validate(['employee_id' => ['required', 'integer', Rule::exists('hr_employees', 'id')->whereNull('deleted_at')]])['employee_id'];

        $document = $this->library->save($request->validated(), (int) $employeeId);

        return $this->detailRedirect($document);
    }

    public function storeShared(Request $request): RedirectResponse
    {
        abort_unless($this->canManage('human-resources.shared-documents'), 401);

        return $this->detailRedirect($this->library->save($this->sharedData($request), null));
    }

    public function show(int $id): View
    {
        $document = $this->find($id);
        $document->load([
            'employee' => fn ($query) => $query->withTrashed(),
            'organizationLinks.organization',
            'contracts',
        ]);

        return view('human_resources::documents.show', [
            'document' => $document,
            'canEdit' => $this->canManage($document->employee_id ? 'human-resources.document-center' : 'human-resources.shared-documents'),
            'history' => DB::table('hr_document_audits')->leftJoin('users', 'users.id', '=', 'hr_document_audits.user_id')
                ->where('document_id', $id)->latest('hr_document_audits.id')->paginate(20, ['action', 'hr_document_audits.created_at', 'users.name']),
        ]);
    }

    public function edit(int $id): View
    {
        $document = $this->find($id, true);

        return $this->form($document, $document->employee_id === null);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $document = $this->find($id, true);
        $data = $document->employee_id
            ? app(EmployeeDocumentRequest::class)->validated()
            : $this->sharedData($request);

        return $this->detailRedirect($this->library->save($data, $document->employee_id, $document));
    }

    public function download(int $id): StreamedResponse
    {
        $document = $this->find($id);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        $this->library->record($document, 'downloaded');

        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->library->delete($this->find($id, true));

        return redirect()->route('admin.human_resources.document_center.index');
    }

    private function form(?EmployeeDocument $document = null, bool $shared = false): View
    {
        $document?->load(['organizationLinks', 'contracts']);

        return view('human_resources::documents.form', [
            'document' => $document,
            'shared' => $shared,
            'types' => $shared ? EmployeeDocument::SHARED_TYPES : EmployeeDocument::EMPLOYEE_TYPES,
            'employees' => ! $shared && ! $document ? app(EmployeeRepository::class)->orderBy('full_name')->all(['id', 'full_name', 'employee_number']) : [],
            'organizations' => $shared ? app(OrganizationRepository::class)->orderBy('name')->all(['id', 'name']) : [],
            'contracts' => $shared ? app(ServiceContractRepository::class)->orderBy('contract_number')->all(['id', 'contract_number', 'title']) : [],
        ]);
    }

    private function sharedData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(EmployeeDocument::SHARED_TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'file' => [$request->isMethod('PUT') ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'organizations' => ['required_without:contracts', 'array', 'max:100'],
            'organizations.*' => ['integer', 'distinct', 'exists:organizations,id'],
            'contracts' => ['required_without:organizations', 'array', 'max:100'],
            'contracts.*' => ['integer', 'distinct', Rule::exists('hr_service_contracts', 'id')->whereNull('deleted_at')],
        ]);
    }

    private function find(int $id, bool $manage = false): EmployeeDocument
    {
        $document = EmployeeDocumentProxy::query()->findOrFail($id);
        $permission = $document->employee_id ? 'human-resources.document-center' : 'human-resources.shared-documents';
        abort_unless($manage ? $this->canManage($permission) : $this->can($permission), 401);

        return $document;
    }

    private function can(string $permission): bool
    {
        return auth()->guard('user')->user()->view_permission === 'global'
            && bouncer()->hasPermission($permission);
    }

    private function canManage(string $permission): bool
    {
        return $this->can('document-center')
            && $this->can($permission)
            && $this->can($permission.'.manage');
    }

    private function detailRedirect(EmployeeDocument $document): RedirectResponse
    {
        return redirect()->route('admin.human_resources.document_center.show', $document->id)
            ->with('success', trans('human_resources::workforce.saved'));
    }
}
