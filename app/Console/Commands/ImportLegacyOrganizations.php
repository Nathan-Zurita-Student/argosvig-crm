<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SplFileObject;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\User\Models\UserProxy;

class ImportLegacyOrganizations extends Command
{
    protected $signature = 'crm:import-organizations {file : UTF-8 semicolon-separated CSV}
        {--source= : Identifier for the old system} {--owner= : Active CRM user ID for new companies}
        {--apply : Write validated companies and their external IDs}';

    protected $description = 'Preview or apply a company import while preserving source identifiers';

    public function handle(OrganizationRepository $organizations): int
    {
        $parameters = Validator::make($this->options(), [
            'source' => ['required', 'regex:/^[a-z0-9_-]{1,40}$/'],
            'owner' => ['required', 'integer', 'min:1'],
        ]);

        if ($parameters->fails() || ! UserProxy::query()->whereKey($this->option('owner'))->where('status', true)->exists()) {
            $this->error($parameters->errors()->first() ?: 'O responsável deve ser um usuário ativo.');

            return self::FAILURE;
        }

        $path = $this->argument('file');

        if (! is_file($path) || ! is_readable($path) || filesize($path) > 5 * 1024 * 1024) {
            $this->error('Informe um CSV local de até 5 MB.');

            return self::FAILURE;
        }

        try {
            $rows = $this->read($path);
            $apply = $this->option('apply');
            $mapping = Cache::lock('crm-legacy-organization-import', 600)->block(1, fn () => DB::transaction(
                fn () => $this->process($rows, $organizations, $apply)
            ));
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        } catch (LockTimeoutException) {
            $this->error('Já existe uma importação de empresas em andamento.');

            return self::FAILURE;
        }

        $this->info($apply ? 'Importação concluída.' : 'Prévia concluída.');
        $this->table(['ID de origem', 'ID no CRM', 'Ação'], $mapping);

        return self::SUCCESS;
    }

    private function read(string $path): array
    {
        $file = new SplFileObject($path);
        $header = $file->fgetcsv(';', '"', '');

        if (is_array($header)) {
            $header[0] = Str::replaceStart("\xEF\xBB\xBF", '', $header[0] ?? '');
        }

        if ($header !== ['external_id', 'name', 'existing_organization_id']) {
            $this->invalid('Cabeçalho esperado: external_id;name;existing_organization_id');
        }

        $rows = [];

        while (! $file->eof()) {
            $cells = $file->fgetcsv(';', '"', '');

            if ($cells === false || $cells === [null]) {
                continue;
            }

            $row = count($rows) + 2;

            if (count($cells) !== 3 || $row > 1001 || ! mb_check_encoding($cells, 'UTF-8')) {
                $this->invalid("Linha {$row} inválida ou limite de 1.000 registros excedido.");
            }

            [$id, $name, $existing] = array_map('trim', $cells);

            if (! preg_match('/\A[A-Za-z0-9_.:-]{1,100}\z/', $id) || $name === '' || mb_strlen($name) > 255
                || preg_match('/[\x00-\x1f\x7f]/', $name) || ($existing !== '' && ! preg_match('/\A[1-9][0-9]{0,9}\z/', $existing))) {
                $this->invalid("Identificador, nome ou vínculo inválido na linha {$row}.");
            }

            if (isset($rows[$id])) {
                $this->invalid("ID de origem repetido na linha {$row}.");
            }

            $rows[$id] = ['name' => $name, 'existing' => $existing === '' ? null : (int) $existing, 'row' => $row];
        }

        if ($rows === []) {
            $this->invalid('O CSV está vazio.');
        }

        return $rows;
    }

    private function process(array $rows, OrganizationRepository $organizations, bool $apply): array
    {
        $source = $this->option('source');
        $links = DB::table('legacy_organization_links')->where('source', $source);
        $mapping = [];
        $used = [];
        $names = [];

        foreach ($rows as $id => $row) {
            $linked = (clone $links)->where('external_id', (string) $id)->first();
            $target = $linked?->organization_id ?? $row['existing'];
            $line = $row['row'];

            if ($linked && (! $linked->organization_id || ($row['existing'] && (int) $target !== $row['existing']))) {
                $this->invalid("O vínculo anterior da linha {$line} exige revisão manual.");
            }

            if ($target && (! OrganizationProxy::query()->whereKey($target)->exists() || isset($used[$target])
                || (! $linked && (clone $links)->where('organization_id', $target)->exists()))) {
                $this->invalid("O destino da linha {$line} está indisponível ou já vinculado nesta origem.");
            }

            $action = $linked ? 'Já vinculado' : ($target ? 'Vincular existente' : 'Criar empresa');

            if (! $target) {
                $nameKey = Str::ascii(Str::lower($row['name']));

                if (isset($names[$nameKey]) || OrganizationProxy::query()->where('name', $row['name'])->exists()) {
                    $this->invalid("Possível duplicidade na linha {$line}. Revise o nome ou informe o ID existente.");
                }

                $names[$nameKey] = true;

                if ($apply) {
                    $target = $organizations->create(['name' => $row['name'], 'user_id' => $this->option('owner'), 'entity_type' => 'organizations'])->id;
                }
            }

            if ($target) {
                $used[$target] = true;
            }

            if ($apply && ! $linked) {
                (clone $links)->insert(['source' => $source, 'external_id' => (string) $id, 'organization_id' => $target]);
            }

            $mapping[] = [$id, $target ?? '—', $action];
        }

        return $mapping;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
