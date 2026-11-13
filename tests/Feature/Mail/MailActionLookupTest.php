<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;

uses(DatabaseTransactions::class);

function mailGridPayloadFor(string $route): array
{
    auth()->guard('user')->setUser(getDefaultAdmin());

    $mailRoute = clone app('router')->getRoutes()->getByName('admin.mail.index');

    $mailRoute->bind(Request::create("/admin/mail/{$route}"));

    request()->setRouteResolver(fn () => $mailRoute);

    return json_decode(app(EmailDataGrid::class)->process()->getContent(), true);
}

it('uses the subject as the mail opener in every folder', function () {
    foreach (['draft', 'outbox', 'inbox', 'trash'] as $route) {
        $payload = mailGridPayloadFor($route);
        $subject = collect($payload['columns'])->firstWhere('index', 'subject');
        $opener = collect($payload['actions'])
            ->first(fn ($action) => in_array($action['index'], ['edit', 'view'], true));

        expect($subject)->not->toBeNull("the {$route} folder should expose its subject column")
            ->and($opener)->toBeNull("the {$route} folder should not duplicate its opener in the actions column");
    }
});
