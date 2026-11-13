<?php

namespace Webkul\HumanResources\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\HumanResources\Contracts\TimekeepingProvider;
use Webkul\HumanResources\Services\TimekeepingImporter;

class SyncAlterdataTimekeeping extends Command
{
    protected $signature = 'hr:sync-alterdata-timekeeping {from : YYYY-MM-DD} {to : YYYY-MM-DD}';

    protected $description = 'Import attendance marks through the configured Alterdata provider';

    public function handle(TimekeepingImporter $importer): int
    {
        if (! $this->laravel->bound(TimekeepingProvider::class)) {
            $this->error('Alterdata API provider is not configured. Use CSV until the official API is available.');

            return self::FAILURE;
        }

        $dates = Validator::make($this->arguments(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if ($dates->fails()) {
            $this->error($dates->errors()->first());

            return self::FAILURE;
        }

        $provider = $this->laravel->make(TimekeepingProvider::class);

        try {
            $result = $importer->import($provider->entries(
                CarbonImmutable::parse($this->argument('from'), config('app.timezone')),
                CarbonImmutable::parse($this->argument('to'), config('app.timezone')),
            ), 'alterdata_api');
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info(trans('human_resources::workforce.imported', $result));

        return self::SUCCESS;
    }
}
