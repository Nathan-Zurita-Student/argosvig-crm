<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Http\Requests\EmployeeDocumentRequest;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Services\DocumentLibrary;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        protected EmployeeRepository $employeeRepository,
        protected DocumentLibrary $library,
    ) {}

    public function store(EmployeeDocumentRequest $request, int $employeeId): RedirectResponse
    {
        $this->authorizeDocuments();
        $employee = $this->employeeRepository->findOrFail($employeeId);
        Event::dispatch('human_resources.employee_document.create.before', $employee);
        $document = $this->library->save($request->validated(), $employeeId);
        Event::dispatch('human_resources.employee_document.create.after', $document);

        return redirect()->route('admin.human_resources.employees.show', $employeeId)
            ->with('success', trans('human_resources::app.documents.messages.created'));
    }

    public function download(int $employeeId, int $documentId): StreamedResponse
    {
        $this->authorizeDocuments();
        $document = EmployeeDocumentProxy::query()->where('employee_id', $employeeId)->findOrFail($documentId);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        $this->library->record($document, 'downloaded');
        Event::dispatch('human_resources.employee_document.downloaded', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(int $employeeId, int $documentId): RedirectResponse
    {
        $this->authorizeDocuments();
        $document = EmployeeDocumentProxy::query()->where('employee_id', $employeeId)->findOrFail($documentId);
        Event::dispatch('human_resources.employee_document.delete.before', $document);
        $this->library->delete($document);
        Event::dispatch('human_resources.employee_document.delete.after', $documentId);

        return redirect()->route('admin.human_resources.employees.show', $employeeId)
            ->with('success', trans('human_resources::app.documents.messages.deleted'));
    }

    private function authorizeDocuments(): void
    {
        abort_unless(auth()->guard('user')->user()->view_permission === 'global'
            && bouncer()->hasPermission('human-resources.employees.documents'), 401);
    }
}
