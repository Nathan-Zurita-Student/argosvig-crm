<?php

use Illuminate\Support\Facades\File;

it('keeps the desktop sidebar control inside the sidebar flow', function () {
    $sidebar = File::get(
        base_path('packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php')
    );

    expect($sidebar)
        ->toContain('mb-2 flex h-9 items-center px-4')
        ->toContain('admin::app.layouts.sidebar.menu')
        ->not->toContain('ltr:-right-3.5')
        ->not->toContain('absolute top-4');
});
