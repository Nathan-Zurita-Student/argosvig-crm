<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Webkul\HumanResources\Models\EmployeeDocument;
use Webkul\HumanResources\Repositories\EmployeeDocumentRepository;

class DocumentLibrary
{
    public function __construct(private EmployeeDocumentRepository $repository) {}

    public function save(array $data, ?int $employeeId, ?EmployeeDocument $existing = null): EmployeeDocument
    {
        $file = $data['file'] ?? null;
        $location = $file ? $this->storeFile($file, $employeeId) : [];
        $previous = null;

        try {
            $document = DB::transaction(function () use ($data, $employeeId, $existing, $location, &$previous) {
                $document = $existing ? $existing->newQuery()->lockForUpdate()->findOrFail($existing->id) : null;

                if ($document && $location) {
                    $previous = [$document->disk, $document->path];
                }

                $attributes = [
                    'employee_id' => $employeeId,
                    'type' => $data['type'],
                    'name' => $data['name'] ?? $document?->name ?? pathinfo($location['original_name'], PATHINFO_FILENAME),
                    'expires_at' => $data['expires_at'] ?? null,
                    'competence' => isset($data['competence']) ? $data['competence'].'-01' : null,
                    'notes' => $data['notes'] ?? null,
                ] + $location;

                if ($document) {
                    $document->update($attributes);
                } else {
                    $document = $this->repository->create($attributes + ['uploaded_by' => auth()->guard('user')->id()]);
                }

                if ($employeeId === null) {
                    $document->organizations()->sync($data['organizations'] ?? []);
                    $document->contracts()->sync($data['contracts'] ?? []);
                }

                $this->record($document, $existing ? ($location ? 'replaced' : 'updated') : 'created');

                return $document;
            });
        } catch (Throwable $exception) {
            if ($location) {
                Storage::disk($location['disk'])->delete($location['path']);
            }

            throw $exception;
        }

        if ($previous) {
            Storage::disk($previous[0])->delete($previous[1]);
        }

        return $document;
    }

    public function delete(EmployeeDocument $document): void
    {
        $deleted = DB::transaction(function () use ($document) {
            $current = $document->newQuery()->lockForUpdate()->findOrFail($document->id);
            $this->record($current, 'deleted');
            $this->repository->delete($current->id);

            return $current;
        });

        Storage::disk($deleted->disk)->delete($deleted->path);
    }

    public function record(EmployeeDocument $document, string $action): void
    {
        DB::table('hr_document_audits')->insert([
            'document_id' => $document->id,
            'user_id' => auth()->guard('user')->id(),
            'action' => $action,
            'created_at' => now(),
        ]);
    }

    /**
     * The disk new documents are written to. Each document keeps the disk it was saved on,
     * so switching this setting never breaks the download of older files.
     */
    public static function disk(): string
    {
        return config('filesystems.documents') ?: 'local';
    }

    private function storeFile(UploadedFile $file, ?int $employeeId): array
    {
        $disk = self::disk();
        $folder = $employeeId ? "employee-documents/{$employeeId}" : 'employee-documents/shared';
        $path = $file->store($folder, $disk);

        if (! $path) {
            throw new RuntimeException('Could not store the document.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ];
    }
}
