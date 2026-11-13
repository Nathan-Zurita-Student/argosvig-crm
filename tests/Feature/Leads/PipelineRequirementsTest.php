<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Lead\Models\Lead;

uses(DatabaseTransactions::class);

it('enforces configured stage requirements for single and bulk moves', function () {
    $admin = getDefaultAdmin();
    $pipeline = securityTestPipeline();
    $initial = $pipeline->stages()->first();
    $target = $pipeline->stages()->create(['name' => 'Review', 'code' => 'review', 'probability' => 50, 'sort_order' => 4, 'required_fields' => ['expected_close_date']]);
    $base = ['user_id' => $admin->id, 'lead_pipeline_id' => $pipeline->id, 'lead_pipeline_stage_id' => $initial->id];
    $missing = Lead::create(['title' => 'Missing close date'] + $base);
    $complete = Lead::create(['title' => 'Has close date', 'expected_close_date' => today()->addWeek()->toDateString()] + $base);

    $this->actingAs($admin, 'user')->get(route('admin.settings.pipelines.edit', $pipeline->id))->assertOk()
        ->assertSee(trans('admin::pipeline_requirements.title'));

    $this->putJson(route('admin.leads.stage.update', $missing->id), ['lead_pipeline_stage_id' => $target->id])
        ->assertUnprocessable()->assertJsonValidationErrors('expected_close_date');

    $this->postJson(route('admin.leads.mass_update'), ['indices' => [$complete->id, $missing->id], 'value' => $target->id])
        ->assertUnprocessable()->assertJsonValidationErrors('expected_close_date');
    expect($complete->refresh()->lead_pipeline_stage_id)->toBe($initial->id);

    $missing->update(['expected_close_date' => today()->addWeek()->toDateString()]);
    $this->putJson(route('admin.leads.stage.update', $missing->id), ['lead_pipeline_stage_id' => $target->id])->assertOk();
    expect($missing->refresh()->lead_pipeline_stage_id)->toBe($target->id);
});
