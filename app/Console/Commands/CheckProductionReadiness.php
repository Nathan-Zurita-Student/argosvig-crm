<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'crm:check-production';

    protected $description = 'Verify the configuration and storage required to run the CRM in production';

    public function handle(Migrator $migrator): int
    {
        $checks = [
            'APP_ENV=production' => app()->isProduction(),
            'APP_DEBUG=false' => ! config('app.debug'),
            'APP_KEY configurada' => filled(config('app.key')),
            'APP_URL com HTTPS' => parse_url(config('app.url'), PHP_URL_SCHEME) === 'https' && filled(parse_url(config('app.url'), PHP_URL_HOST)),
            'SESSION_SECURE_COOKIE=true' => config('session.secure') === true,
            'Banco acessível' => $this->databaseAvailable(),
        ];

        $checks['Migrações aplicadas'] = $checks['Banco acessível'] && $this->migrationsApplied($migrator);

        foreach (['local', 'public'] as $disk) {
            $checks['Leitura e gravação no disco '.$disk] = $this->storageAvailable($disk);
        }

        $this->table(['Verificação', 'Resultado'], collect($checks)->map(fn ($ok, $label) => [$label, $ok ? 'OK' : 'PENDENTE'])->values()->all());

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function databaseAvailable(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function migrationsApplied(Migrator $migrator): bool
    {
        try {
            $paths = [...$migrator->paths(), database_path('migrations')];
            $files = array_keys($migrator->getMigrationFiles($paths));

            return $migrator->repositoryExists() && array_diff($files, $migrator->getRepository()->getRan()) === [];
        } catch (Throwable) {
            return false;
        }
    }

    private function storageAvailable(string $name): bool
    {
        $path = 'readiness-'.Str::uuid();

        try {
            $disk = Storage::disk($name);

            return $disk->put($path, 'ready') && $disk->get($path) === 'ready' && $disk->delete($path);
        } catch (Throwable) {
            return false;
        }
    }
}
