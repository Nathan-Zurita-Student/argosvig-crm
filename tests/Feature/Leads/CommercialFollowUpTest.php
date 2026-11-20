<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Activity\Models\Activity;
use Webkul\Admin\Helpers\CommercialLabel;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('localizes seeded labels without changing customized names', function () {
    app()->setLocale('pt_BR');

    expect(CommercialLabel::display('Negotiation', 'pipeline.pipeline-stages'))->toBe('Negociação')
        ->and(CommercialLabel::display('Existing Business', 'type'))->toBe('Negócio Existente')
        ->and(CommercialLabel::display('Negociação jurídica', 'pipeline.pipeline-stages'))->toBe('Negociação jurídica');
});

it('shows only scoped open opportunities with no future incomplete activity', function () {
    $actor = User::create([
        'name' => 'Follow-up test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => 'individual',
        'role_id' => Role::create([
            'name' => fake()->uuid(), 'description' => 'Follow-up test', 'permission_type' => 'custom',
            'permissions' => ['leads', 'leads.view', 'activities', 'activities.edit'],
        ])->id,
    ]);
    $pipeline = securityTestPipeline();
    $base = ['user_id' => $actor->id, 'lead_pipeline_id' => $pipeline->id, 'lead_pipeline_stage_id' => $pipeline->stages()->value('id')];
    $missing = Lead::create(['title' => 'Lead without action'] + $base);
    $scheduled = Lead::create(['title' => 'Scheduled lead'] + $base);
    $closed = Lead::create(['title' => 'Closed lead', 'closed_at' => now()->subDay()] + $base);
    $other = Lead::create(['title' => 'Foreign lead', 'user_id' => getDefaultAdmin()->id] + $base);

    $activity = ['type' => 'call', 'is_done' => false, 'user_id' => $actor->id];
    $next = Activity::create(['title' => 'Upcoming call', 'schedule_from' => now()->addDay(), 'schedule_to' => now()->addDay()->addHour()] + $activity);
    $scheduled->activities()->attach($next->id);
    $private = Activity::create(['title' => 'Private call', 'user_id' => getDefaultAdmin()->id, 'schedule_from' => now()->subDays(3), 'schedule_to' => now()->subDays(3)->addHour()] + $activity);
    $scheduled->activities()->attach($private->id);
    $late = Activity::create(['title' => 'Overdue call', 'schedule_from' => now()->subDay(), 'schedule_to' => now()->subHour()] + $activity);
    $missing->activities()->attach($late->id);
    Activity::create(['title' => 'Foreign overdue call', 'user_id' => getDefaultAdmin()->id, 'schedule_from' => now()->subDay(), 'schedule_to' => now()->subHour()] + $activity);

    $this->actingAs($actor, 'user')->get(route('admin.leads.follow_up'))->assertOk()
        ->assertSee(route('admin.leads.view', $missing->id))->assertSee($missing->title)
        ->assertDontSee($scheduled->title)->assertDontSee($closed->title)->assertDontSee($other->title);

    $this->get(route('admin.activities.follow_up'))->assertOk()
        ->assertSee(route('admin.activities.edit', $late->id))->assertSee($late->title)
        ->assertDontSee($next->title)->assertDontSee('Foreign overdue call');

    $board = $this->getJson(route('admin.leads.get', ['pipeline_id' => $pipeline->id]))->assertOk()->json();
    $cards = collect($board)->flatMap(fn ($stage) => $stage['leads']['data'])->keyBy('id');

    expect($cards[$scheduled->id]['next_activity_at'])->toBe($next->schedule_from->format('d/m/Y H:i'))
        ->and($cards[$missing->id]['next_activity_at'])->toBe($late->schedule_from->format('d/m/Y H:i'));

    $actor->role->update(['permissions' => ['leads', 'activities']]);
    $actor->unsetRelation('role');
    $this->get(route('admin.leads.follow_up'))->assertUnauthorized();
});
