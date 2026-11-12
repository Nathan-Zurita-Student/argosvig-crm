<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Http\Requests\EmployeeDocumentRequest;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;
use Webkul\HumanResources\Repositories\EmployeeDocumentRepository;
use Webkul\HumanResources\Repositories\EmployeeRepository;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        protected EmployeeRepository $employeeRepository,
        protected EmployeeDocumentRepository $documentRepository,
    ) {}

    public function store(EmployeeDocumentRequest $request, int $employeeId): RedirectResponse
    {
        $employee = $this->employeeRepository->findOrFail($employeeId);
        $file = $request->file('file');

        Event::dispatch('human_resources.employee_document.create.before', $employee);

        $document = DB::transaction(function () use ($employee, $file, $request) {
            $path = $file->store("employee-documents/{$employee->id}", 'local');

            return $this->documentRepository->create([
                'employee_id' => $employee->id,
                'type' => $request->string('type')->toString(),
                'name' => $request->filled('name') ? $request->string('name')->toString() : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'expires_at' => $request->date('expires_at'),
                'status' => 'valid',
                'uploaded_by' => auth()->guard('user')->id(),
            ]);
        });

        Event::dispatch('human_resources.employee_document.create.after', $document);

        session()->flash('success', trans('human_resources::app.documents.messages.created'));

        return redirect()->route('admin.human_resources.employees.show', $employee->id);
    }

    public function download(int $employeeId, int $documentId): StreamedResponse
    {
        $document = EmployeeDocumentProxy::query()
            ->where('employee_id', $employeeId)
            ->findOrFail($documentId);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        Event::dispatch('human_resources.employee_document.downloaded', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(int $employeeId, int $documentId): RedirectResponse
    {
        $document = EmployeeDocumentProxy::query()
            ->where('employee_id', $employeeId)
            ->findOrFail($documentId);

        Event::dispatch('human_resources.employee_document.delete.before', $document);

        DB::transaction(function () use ($document) {
            Storage::disk($document->disk)->delete($document->path);
            $this->documentRepository->delete($document->id);
        });

        Event::dispatch('human_resources.employee_document.delete.after', $documentId);

        session()->flash('success', trans('human_resources::app.documents.messages.deleted'));

        return redirect()->route('admin.human_resources.employees.show', $employeeId);
    }
}
