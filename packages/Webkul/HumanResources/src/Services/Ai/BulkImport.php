<?php

namespace Webkul\HumanResources\Services\Ai;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\HumanResources\Models\DepartmentProxy;
use Webkul\HumanResources\Models\EmployeeProxy;
use Webkul\HumanResources\Models\WorkSiteProxy;
use Webkul\Product\Models\ProductProxy;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Turns the rows of an attached spreadsheet into CRM records.
 *
 * The AI only says which kind of record the file holds and which column feeds each field.
 * Everything else happens here, for every row: values are normalized, validated with the
 * same rules as the forms, compared with what is already saved, shown as a preview and
 * created only when the person confirms.
 */
class BulkImport
{
    public const MAX_ROWS = 1000;

    private const TTL_MINUTES = 30;

    /**
     * permission: ACL key to create; unique: field compared with saved records;
     * listing: where the created records are shown.
     */
    public const TYPES = [
        'organization' => [
            'permission' => 'contacts.organizations.create',
            'listing' => 'admin.contacts.organizations.index',
            'unique' => 'name',
            'fields' => [
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'postcode' => ['nullable', 'string', 'max:20'],
                'country' => ['nullable', 'string', 'max:100'],
            ],
        ],
        'person' => [
            'permission' => 'contacts.persons.create',
            'listing' => 'admin.contacts.persons.index',
            'unique' => 'email',
            'fields' => [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'phone' => ['nullable', 'digits_between:8,15'],
                'job_title' => ['nullable', 'string', 'max:100'],
                'organization_name' => ['nullable', 'string', 'max:255'],
            ],
        ],
        'product' => [
            'permission' => 'products.create',
            'listing' => 'admin.products.index',
            'unique' => 'sku',
            'fields' => [
                'name' => ['required', 'string', 'max:255'],
                'sku' => ['required', 'string', 'max:100'],
                'price' => ['required', 'numeric', 'min:0'],
                'quantity' => ['nullable', 'integer', 'min:0'],
                'description' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'employee' => [
            'permission' => 'human-resources.employees.create',
            'listing' => 'admin.human_resources.employees.index',
            'unique' => 'employee_number',
            'also_unique' => ['cpf' => 'hr_employees'],
            'fields' => [
                'employee_number' => ['required', 'string', 'max:50'],
                'full_name' => ['required', 'string', 'max:255'],
                'cpf' => ['nullable', 'string', 'size:14'],
                'email' => ['nullable', 'email:rfc', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'job_title' => ['nullable', 'string', 'max:255'],
                'hire_date' => ['nullable', 'date_format:Y-m-d'],
                'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'department' => ['nullable', 'string', 'max:255'],
            ],
        ],
        'department' => [
            'permission' => 'human-resources.departments.create',
            'listing' => 'admin.human_resources.departments.index',
            'unique' => 'name',
            'also_unique' => ['code' => 'hr_departments'],
            'fields' => [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['nullable', 'string', 'max:30'],
                'description' => ['nullable', 'string', 'max:2000'],
            ],
        ],
        'site' => [
            'permission' => 'human-resources.work-sites.create',
            'listing' => 'admin.human_resources.work_sites.index',
            'unique' => 'code',
            'fields' => [
                'code' => ['required', 'string', 'max:100'],
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'size:2'],
                'required_staff' => ['nullable', 'integer', 'min:1', 'max:10000'],
            ],
        ],
    ];

    public function __construct(private AssistantFiles $files) {}

    /**
     * Record types this user may create, with their fields, as the AI should know them.
     */
    public function available(): array
    {
        return collect(self::TYPES)
            ->filter(fn (array $type) => bouncer()->hasPermission($type['permission']))
            ->map(fn (array $type) => collect($type['fields'])->map(fn ($rules) => in_array('required', $rules, true) ? 'obrigatório' : 'opcional')->all())
            ->all();
    }

    /**
     * Builds the preview of an import and keeps its plan for the confirmation.
     */
    public function preview(string $fileToken, array $file, string $type, array $mapping, int $userId): ?array
    {
        if (! isset($this->available()[$type]) || $file['kind'] !== 'table') {
            return null;
        }

        $columns = $this->columns($file['headers'], $type, $mapping);

        if (! $columns) {
            return null;
        }

        $result = $this->evaluate($file, $type, $columns);
        $token = (string) Str::uuid();

        Cache::put($this->key($userId, $token), ['file' => $fileToken, 'type' => $type, 'columns' => $columns], now()->addMinutes(self::TTL_MINUTES));

        return [
            'token' => $token,
            'type' => $type,
            'label' => trans('human_resources::ai.import.types.'.$type),
            'total' => count($result['rows']),
            'new' => count($result['new']),
            'existing' => $result['existing'],
            'invalid' => count($result['errors']),
            'truncated' => $result['truncated'],
            'columns' => collect($columns)->map(fn ($index, $field) => [
                'field' => trans('human_resources::ai.import.fields.'.$field),
                'header' => $file['headers'][$index],
            ])->values()->all(),
            'sample' => array_map(
                fn (array $record) => collect($columns)->keys()->mapWithKeys(fn ($field) => [trans('human_resources::ai.import.fields.'.$field) => (string) ($record[$field] ?? '')])->all(),
                array_slice($result['new'], 0, 5),
            ),
            'errors' => array_slice($result['errors'], 0, 8),
        ];
    }

    /**
     * Creates the new records of a confirmed preview, checking everything again.
     */
    public function run(string $token, int $userId): array
    {
        $plan = Cache::get($this->key($userId, $token));
        $file = $plan ? $this->files->find($plan['file'], $userId) : null;

        if (! $file) {
            throw ValidationException::withMessages(['token' => trans('human_resources::ai.import.expired')]);
        }

        $type = $plan['type'];

        abort_unless(bouncer()->hasPermission(self::TYPES[$type]['permission']), 401);

        $result = $this->evaluate($file, $type, $plan['columns']);

        DB::transaction(function () use ($result, $type, $userId) {
            foreach ($result['new'] as $record) {
                $this->create($type, $record, $userId);
            }
        });

        Cache::forget($this->key($userId, $token));

        return [
            'created' => count($result['new']),
            'skipped' => $result['existing'] + count($result['errors']),
            'url' => route(self::TYPES[$type]['listing']),
            'message' => trans_choice('human_resources::ai.import.done', count($result['new']), [
                'count' => count($result['new']),
                'type' => mb_strtolower(trans('human_resources::ai.import.types.'.$type)),
            ]),
        ];
    }

    /**
     * Field => column index, from the header names the AI chose. Unknown fields and headers
     * are dropped; the import needs every required field.
     */
    private function columns(array $headers, string $type, array $mapping): array
    {
        $normalized = array_map(fn ($header) => Str::lower(Str::squish((string) $header)), $headers);
        $columns = [];

        foreach (self::TYPES[$type]['fields'] as $field => $rules) {
            $header = Str::lower(Str::squish((string) ($mapping[$field] ?? '')));
            $index = $header === '' ? false : array_search($header, $normalized, true);

            if ($index !== false) {
                $columns[$field] = $index;
            }
        }

        $required = collect(self::TYPES[$type]['fields'])->filter(fn ($rules) => in_array('required', $rules, true))->keys();

        return $required->every(fn ($field) => isset($columns[$field])) ? $columns : [];
    }

    private function evaluate(array $file, string $type, array $columns): array
    {
        $rows = array_slice($file['rows'], 0, self::MAX_ROWS);
        $definition = self::TYPES[$type];
        $unique = $definition['unique'];
        $seen = [];
        $new = [];
        $errors = [];
        $existing = 0;

        // Every field is present, empty when the spreadsheet has no column for it.
        $records = array_map(fn (array $row) => $this->normalize($type, collect($definition['fields'])
            ->map(fn ($rules, $field) => isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null)->all()), $rows);
        $saved = $this->savedKeys($type, array_filter(array_column($records, $unique)));

        // Other values the database keeps unique, such as an employee CPF: a repeated one
        // is reported on its row instead of failing the whole import.
        $taken = collect($definition['also_unique'] ?? [])->map(fn ($table, $field) => DB::table($table)
            ->whereIn($field, array_filter(array_column($records, $field)))->pluck($field)->flip()->all())->all();

        foreach ($records as $position => $record) {
            $line = $position + 2;
            $validator = Validator::make($record, $definition['fields'], [], collect($definition['fields'])->keys()
                ->mapWithKeys(fn ($field) => [$field => trans('human_resources::ai.import.fields.'.$field)])->all());

            if ($validator->fails()) {
                $errors[] = ['row' => $line, 'message' => $validator->errors()->first()];

                continue;
            }

            foreach ($taken as $field => $values) {
                if (filled($record[$field]) && isset($values[$record[$field]])) {
                    $errors[] = ['row' => $line, 'message' => trans('human_resources::ai.import.duplicate_value', [
                        'field' => trans('human_resources::ai.import.fields.'.$field), 'value' => $record[$field],
                    ])];

                    continue 2;
                }
            }

            $key = Str::lower((string) $record[$unique]);

            if (isset($saved[$key]) || isset($seen[$key])) {
                $existing++;

                continue;
            }

            $seen[$key] = true;

            foreach (array_keys($taken) as $field) {
                if (filled($record[$field])) {
                    $taken[$field][$record[$field]] = true;
                }
            }

            $new[] = $record;
        }

        return [
            'rows' => $rows,
            'new' => $new,
            'errors' => $errors,
            'existing' => $existing,
            'truncated' => count($file['rows']) > self::MAX_ROWS || ($file['truncated'] ?? false),
        ];
    }

    /**
     * Spreadsheet values as the forms expect them: Brazilian dates and amounts, masked CPF,
     * phone digits and upper-case state.
     */
    private function normalize(string $type, array $record): array
    {
        $record = array_map(fn ($value) => filled($value) ? Str::squish((string) $value) : null, $record);

        foreach (['price'] as $field) {
            if (isset($record[$field])) {
                $record[$field] = $this->decimal($record[$field]);
            }
        }

        foreach (['quantity', 'required_staff'] as $field) {
            if (isset($record[$field])) {
                $number = $this->decimal($record[$field]);
                $record[$field] = is_numeric($number) && floor((float) $number) == $number ? (int) $number : $record[$field];
            }
        }

        foreach (['hire_date', 'birth_date'] as $field) {
            if (isset($record[$field])) {
                $record[$field] = $this->date($record[$field]);
            }
        }

        if (isset($record['email'])) {
            $record['email'] = Str::lower($record['email']);
        }

        if ($type === 'person' && isset($record['phone'])) {
            $record['phone'] = preg_replace('/\D/', '', $record['phone']);
        }

        if (isset($record['cpf']) && strlen($digits = preg_replace('/\D/', '', $record['cpf'])) === 11) {
            $record['cpf'] = vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($digits));
        }

        if ($type === 'site' && isset($record['state'])) {
            $record['state'] = Str::upper($record['state']);
        }

        if ($type === 'employee' && isset($record['employee_number'])) {
            $record['employee_number'] = Str::upper($record['employee_number']);
        }

        return $record;
    }

    private function decimal(string $value): string
    {
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        // "1.234,56" or "1234,56" use the comma for decimals.
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }

    private function date(string $value): string
    {
        // Excel stores dates as days since 1899-12-30.
        if (preg_match('/^\d{5}$/', $value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    /**
     * Unique values already saved, lower-cased, among the ones in the file.
     */
    private function savedKeys(string $type, array $values): array
    {
        $values = array_values(array_unique(array_map(fn ($value) => Str::lower((string) $value), $values)));

        if (! $values) {
            return [];
        }

        $found = match ($type) {
            'organization' => OrganizationProxy::query()->whereIn(DB::raw('LOWER(name)'), $values)->pluck('name'),
            'product' => ProductProxy::query()->whereIn(DB::raw('LOWER(sku)'), $values)->pluck('sku'),
            'employee' => EmployeeProxy::query()->whereIn(DB::raw('UPPER(employee_number)'), array_map('strtoupper', $values))->pluck('employee_number'),
            'department' => DepartmentProxy::query()->whereIn(DB::raw('LOWER(name)'), $values)->pluck('name'),
            'site' => WorkSiteProxy::query()->whereIn(DB::raw('LOWER(code)'), $values)->pluck('code'),
            'person' => collect($values)->filter(fn ($email) => PersonProxy::query()
                ->whereRaw('JSON_SEARCH(LOWER(emails), "one", ?) IS NOT NULL', [$email])->exists()),
        };

        return collect($found)->mapWithKeys(fn ($value) => [Str::lower((string) $value) => true])->all();
    }

    private function create(string $type, array $record, int $userId): void
    {
        match ($type) {
            'organization' => app(OrganizationRepository::class)->create([
                'name' => $record['name'],
                'address' => array_filter(Arr::only($record, ['address', 'city', 'state', 'postcode', 'country'])) ?: null,
                'user_id' => $userId,
                'entity_type' => 'organizations',
            ]),
            'person' => app(PersonRepository::class)->create(array_filter([
                'name' => $record['name'],
                'emails' => [['value' => $record['email'], 'label' => 'work']],
                'contact_numbers' => $record['phone'] ? [['value' => $record['phone'], 'label' => 'work']] : null,
                'job_title' => $record['job_title'],
                'organization_name' => $record['organization_name'],
                'user_id' => $userId,
                'entity_type' => 'persons',
            ], fn ($value) => $value !== null)),
            'product' => app(ProductRepository::class)->create([
                'name' => $record['name'],
                'sku' => $record['sku'],
                'price' => $record['price'],
                'quantity' => $record['quantity'] ?? 0,
                'description' => $record['description'],
                'entity_type' => 'products',
            ]),
            'employee' => EmployeeProxy::query()->create([
                ...array_filter(Arr::except($record, ['department']), fn ($value) => $value !== null),
                'department_id' => $record['department'] ? DepartmentProxy::query()
                    ->where(fn ($query) => $query->where('name', $record['department'])->orWhere('code', $record['department']))->value('id') : null,
                'status' => 'active',
            ]),
            'department' => DepartmentProxy::query()->create([...array_filter($record, fn ($value) => $value !== null), 'is_active' => true]),
            'site' => WorkSiteProxy::query()->create([...array_filter($record, fn ($value) => $value !== null), 'required_staff' => $record['required_staff'] ?? 1, 'status' => 'active']),
        };
    }

    private function key(int $userId, string $token): string
    {
        return 'crm-ai-import:'.$userId.':'.$token;
    }
}
