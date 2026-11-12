<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('creates an operational work site', function () {
    $admin = getDefaultAdmin();
    test()->actingAs($admin)->get(route('admin.human_resources.work_sites.index'))->assertSuccessful()->assertSee(trans('human_resources::app.work_sites.index.title'));
    test()->actingAs($admin)->get(route('admin.human_resources.work_sites.create'))->assertSuccessful()->assertSee(trans('human_resources::app.work_sites.create.title'));
    $code = fake()->unique()->bothify('PST-####');
    test()->actingAs($admin)->post(route('admin.human_resources.work_sites.store'), ['code' => $code, 'name' => 'Posto Operacional', 'city' => 'Vitória', 'state' => 'ES', 'required_staff' => 12, 'status' => 'active'])->assertRedirect(route('admin.human_resources.work_sites.index'));
    $this->assertDatabaseHas('hr_work_sites', ['code' => $code, 'required_staff' => 12, 'status' => 'active']);
});
