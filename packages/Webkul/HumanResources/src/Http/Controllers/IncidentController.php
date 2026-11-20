<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\IncidentDataGrid;
use Webkul\HumanResources\Http\Requests\IncidentRequest;
use Webkul\HumanResources\Models\Incident;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Repositories\IncidentRepository;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\User\Repositories\UserRepository;

class IncidentController extends Controller
{
    public function __construct(protected IncidentRepository $repository, protected EmployeeRepository $employeeRepository, protected ServiceContractRepository $contractRepository, protected UserRepository $userRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(IncidentDataGrid::class)->process() : view('human_resources::incidents.index');
    }

    public function create(): View
    {
        return view('human_resources::incidents.create', $this->formData());
    }

    public function show(int $id): View
    {
        $incident = $this->repository->with(['employee', 'serviceContract.organization', 'assignee', 'reporter'])->findOrFail($id);
        $images = $incident->images()->orderByDesc('id')->paginate(24, ['*'], 'images_page')->fragment('incident-images');

        return view('human_resources::incidents.show', compact('incident', 'images'));
    }

    public function image(int $id, int $imageId): StreamedResponse
    {
        $incident = $this->repository->findOrFail($id);
        $image = $incident->images()->findOrFail($imageId);
        $disk = Storage::disk($image->disk);

        abort_unless($disk->exists($image->path), 404);

        return $disk->response($image->path, null, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function store(IncidentRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image_count', 'images']);
        $data['code'] = $this->nextCode();
        $data['reported_by'] = auth()->guard('user')->id();
        if (in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['resolved_at'] = now();
        }
        $incident = $this->saveWithImages($data, $request->file('images', []));
        session()->flash('success', trans('human_resources::app.incidents.messages.created'));

        return redirect()->route('admin.human_resources.incidents.show', $incident->id);
    }

    public function edit(int $id): View
    {
        return view('human_resources::incidents.edit', [...$this->formData(), 'incident' => $this->repository->findOrFail($id)]);
    }

    public function update(IncidentRequest $request, int $id): RedirectResponse
    {
        $data = $request->safe()->except(['image_count', 'images']);
        $data['resolved_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? now() : null;
        $this->saveWithImages($data, $request->file('images', []), $id);
        session()->flash('success', trans('human_resources::app.incidents.messages.updated'));

        return redirect()->route('admin.human_resources.incidents.show', $id);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => trans('human_resources::app.incidents.messages.deleted')]);
    }

    private function saveWithImages(array $data, array $images, ?int $id = null): Incident
    {
        $disk = config('filesystems.documents') === 's3' ? 's3' : 'local';
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $images, $id, $disk, &$storedPaths) {
                $incident = $id === null ? $this->repository->create($data) : $this->repository->update($data, $id);

                foreach ($images as $image) {
                    $path = $image->store("incident-images/{$incident->id}", ['disk' => $disk, 'visibility' => 'private']);

                    if ($path === false) {
                        throw ValidationException::withMessages(['images' => trans('human_resources::app.incidents.images.storage_error')]);
                    }

                    $storedPaths[] = $path;
                    $incident->images()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => Str::limit($image->getClientOriginalName(), 255, ''),
                        'mime_type' => $image->getMimeType(),
                    ]);
                }

                return $incident;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                try {
                    Storage::disk($disk)->delete($storedPaths);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            if ($exception instanceof UnableToWriteFile) {
                report($exception);

                throw ValidationException::withMessages(['images' => trans('human_resources::app.incidents.images.storage_error')]);
            }

            throw $exception;
        }
    }

    private function nextCode(): string
    {
        return 'OCO-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
    }

    private function formData(): array
    {
        return ['employees' => $this->employeeRepository->orderBy('full_name')->all(), 'contracts' => $this->contractRepository->orderBy('contract_number')->all(), 'users' => $this->userRepository->orderBy('name')->all()];
    }
}
