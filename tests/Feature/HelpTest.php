<?php

use Illuminate\Support\Facades\Route;

it('does not expose the placeholder help module', function () {
    $admin = getDefaultAdmin();

    expect(Route::has('admin.help.index'))->toBeFalse();

    expect(
        collect(menu()->getItems('admin'))->contains(
            fn ($item) => $item->getKey() === 'help'
        )
    )->toBeFalse();

    test()->actingAs($admin)
        ->get('/admin/help')
        ->assertNotFound();
});
