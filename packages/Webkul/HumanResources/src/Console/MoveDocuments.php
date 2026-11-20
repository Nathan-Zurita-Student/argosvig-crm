<?php

namespace Webkul\HumanResources\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Throwable;
use Webkul\HumanResources\Models\EmployeeDocument;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;
use Webkul\HumanResources\Services\DocumentLibrary;

/**
 * Copies the HR files kept on another disk (usually the server's own) to the documents disk,
 * checks each copy and only then points the record at it and removes the original.
 */
class MoveDocuments extends Command
{
    protected $signature = 'hr:move-documents
        {disk? : Target disk; defaults to FILESYSTEM_DOCUMENTS_DISK}
        {--apply : Move the files; without it the command only shows a preview}';

    protected $description = 'Move the stored HR documents to the documents disk (for example a private S3 bucket)';

    public function handle(): int
    {
        $target = $this->argument('disk') ?: DocumentLibrary::disk();

        if (! array_key_exists($target, config('filesystems.disks', []))) {
            $this->error(trans('human_resources::workforce.move.unknown_disk', ['disk' => $target]));

            return self::FAILURE;
        }

        $query = EmployeeDocumentProxy::query()->where('disk', '!=', $target);
        $count = (clone $query)->count();

        if (! $count) {
            $this->info(trans('human_resources::workforce.move.nothing', ['disk' => $target]));

            return self::SUCCESS;
        }

        $this->line(trans('human_resources::workforce.move.preview', [
            'count' => $count,
            'size' => Number::fileSize((int) (clone $query)->sum('size')),
            'disk' => $target,
        ]));

        if (! $this->option('apply')) {
            $this->line(trans('human_resources::workforce.move.apply_hint'));

            return self::SUCCESS;
        }

        $moved = 0;
        $failed = 0;

        $query->chunkById(100, function ($documents) use ($target, &$moved, &$failed) {
            foreach ($documents as $document) {
                if ($this->move($document, $target)) {
                    $moved++;
                } else {
                    $failed++;
                    $this->warn(trans('human_resources::workforce.move.failed', ['id' => $document->id]));
                }
            }
        });

        $this->info(trans('human_resources::workforce.move.done', ['moved' => $moved, 'failed' => $failed]));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function move(EmployeeDocument $document, string $target): bool
    {
        $source = Storage::disk($document->disk);
        $destination = Storage::disk($target);

        try {
            if (! $source->exists($document->path)) {
                return false;
            }

            $stream = $source->readStream($document->path);
            $destination->writeStream($document->path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($destination->size($document->path) !== $source->size($document->path)) {
                $destination->delete($document->path);

                return false;
            }

            $document->update(['disk' => $target]);

            // The record already points at the copy; a leftover original is only reported.
            rescue(fn () => $source->delete($document->path));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
