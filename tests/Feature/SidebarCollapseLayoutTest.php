<?php

use Illuminate\Support\Facades\File;

it('keeps the desktop sidebar control discreetly in the header', function () {
    $sidebar = File::get(
        base_path('packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php')
    );
    $header = File::get(
        base_path('packages/Webkul/Admin/src/Resources/views/components/layouts/header/index.blade.php')
    );
    $layout = File::get(
        base_path('packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php')
    );

    expect($header)->toContain('<v-sidebar-collapse></v-sidebar-collapse>')
        ->and($sidebar)->toContain('overflow-y-auto')
        ->toContain('sidebar-rounded grid w-full gap-1')
        ->not->toContain('ltr:-right-3.5')
        ->not->toContain('absolute top-4')
        ->and($layout)->not->toContain('showPoweredBy')
        ->not->toContain('general.settings.footer.label');
});
