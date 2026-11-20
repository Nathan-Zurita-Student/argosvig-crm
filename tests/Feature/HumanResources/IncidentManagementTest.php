<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Admin\Security\TwoFactorAuthentication;
use Webkul\HumanResources\Http\Requests\IncidentRequest;
use Webkul\HumanResources\Models\Incident;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $admin = getDefaultAdmin();

    $this->actingAs($admin, 'user')->withSession([
        TwoFactorAuthentication::SESSION_KEY => app(TwoFactorAuthentication::class)->fingerprint($admin),
    ]);
});

function incidentImageTestData(): array
{
    return [
        'title' => 'Evidências do posto',
        'description' => 'Registro fotográfico do posto.',
        'occurred_at' => now()->format('Y-m-d H:i:s'),
        'severity' => 'medium',
        'status' => 'open',
    ];
}

function incidentImageViewer(array $permissions): User
{
    $role = Role::create([
        'name' => (string) Str::uuid(),
        'description' => 'Incident image access',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'Incident viewer',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('test-secret'),
        'status' => 1,
        'role_id' => $role->id,
    ]);
}

it('registers and displays an operational incident', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.human_resources.incidents.index'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.incidents.index.title'));

    test()->actingAs($admin)
        ->get(route('admin.human_resources.incidents.create'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.incidents.create.title'));

    test()->actingAs($admin)
        ->post(route('admin.human_resources.incidents.store'), [
            'title' => 'Acesso não autorizado',
            'description' => 'Tentativa de acesso identificada no posto principal.',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'location' => 'Portaria principal',
            'severity' => 'high',
            'status' => 'investigating',
            'assigned_to' => $admin->id,
        ])
        ->assertRedirect();

    $incident = Incident::query()->latest('id')->firstOrFail();

    expect($incident->code)->toStartWith('OCO-')
        ->and($incident->reported_by)->toBe($admin->id);

    test()->actingAs($admin)
        ->get(route('admin.human_resources.incidents.show', $incident->id))
        ->assertSuccessful()
        ->assertSee($incident->code)
        ->assertSee('Acesso não autorizado');
});

it('requires a resolution before resolving an incident', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->post(route('admin.human_resources.incidents.store'), [
            'title' => 'Ocorrência sem resolução',
            'description' => 'Descrição da ocorrência.',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'severity' => 'medium',
            'status' => 'resolved',
        ])
        ->assertSessionHasErrors('resolution');
});

it('attaches multiple private images at creation and retains them when adding another batch', function () {
    config(['filesystems.documents' => 'local']);
    $disk = Storage::fake('local');
    $public = Storage::fake('public');
    $data = incidentImageTestData();
    $first = UploadedFile::fake()->image('entrada.jpg')->size(20 * 1024);
    $second = UploadedFile::fake()->image('posto.png');

    $this->post(route('admin.human_resources.incidents.store'), [
        ...$data,
        'image_count' => 2,
        'images' => [$first, $second],
    ])->assertRedirect();

    $incident = Incident::query()->latest('id')->firstOrFail();
    $image = $incident->images()->where('original_name', 'entrada.jpg')->firstOrFail();
    $limit = IncidentRequest::imageBatchLimit();
    $nextBatch = array_map(fn (int $index) => UploadedFile::fake()->image("evidencia-{$index}.png"), range(1, $limit));

    $this->get(route('admin.human_resources.incidents.edit', $incident))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false);

    $this->put(route('admin.human_resources.incidents.update', $incident), [
        ...$data,
        'image_count' => $limit,
        'images' => $nextBatch,
    ])->assertRedirect(route('admin.human_resources.incidents.show', $incident));

    expect($incident->images()->count())->toBe(2 + $limit);
    $disk->assertExists($image->path);
    $public->assertMissing($image->path);

    $url = route('admin.human_resources.incidents.images.show', [$incident, $image]);
    $page = (int) ceil((2 + $limit) / 24);

    $this->get(route('admin.human_resources.incidents.show', [$incident, 'images_page' => $page]))
        ->assertOk()
        ->assertSee($url)
        ->assertSee('entrada.jpg');

    $other = Incident::create([...$data, 'code' => 'OCO-'.Str::random(12)]);

    $this->get(route('admin.human_resources.incidents.images.show', [$other, $image]))->assertNotFound();

    $this->actingAs(incidentImageViewer(['human-resources.incidents', 'human-resources.incidents.view']), 'user')
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertStreamedContent($disk->get($image->path));

    $this->actingAs(incidentImageViewer(['human-resources.incidents']), 'user')
        ->get($url)
        ->assertUnauthorized();
});

it('rejects invalid or incomplete image submissions without creating an incident', function () {
    config(['filesystems.documents' => 'local']);
    $disk = Storage::fake('local');
    $data = incidentImageTestData();
    $url = route('admin.human_resources.incidents.store');

    $this->post($url, [
        ...$data,
        'image_count' => 1,
        'images' => [UploadedFile::fake()->createWithContent('evidencia.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')],
    ])->assertSessionHasErrors('images.0');

    $this->post($url, [
        ...$data,
        'image_count' => 1,
        'images' => [UploadedFile::fake()->image('grande.jpg')->size(20 * 1024 + 1)],
    ])->assertSessionHasErrors('images.0');

    $this->post($url, [
        ...$data,
        'image_count' => 1,
    ])->assertSessionHasErrors('images');

    $this->assertDatabaseMissing('hr_incidents', ['title' => $data['title']]);
    expect($disk->allFiles())->toBeEmpty();
});
