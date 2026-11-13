<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\HumanResources\Models\QuoteVersion;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('keeps sent quote versions and links only the accepted snapshot to one draft contract', function () {
    $admin = getDefaultAdmin();
    $company = Organization::create(['name' => 'Quote customer']);
    $person = Person::create(['name' => 'Quote contact', 'organization_id' => $company->id, 'user_id' => $admin->id]);
    $quote = Quote::create(['subject' => 'Original offer', 'person_id' => $person->id, 'user_id' => $admin->id, 'grand_total' => 100]);

    $show = route('admin.quotes.follow_up.show', $quote->id);
    $store = route('admin.quotes.follow_up.store', $quote->id);
    $this->actingAs($admin, 'user')->get($show)->assertOk();
    $this->post($store, ['status' => 'sent', 'version' => 0])->assertRedirect($show);
    $this->get(route('admin.quotes.follow_up'))->assertOk()->assertSee($quote->subject);

    $quote->update(['subject' => 'Revised offer', 'grand_total' => 200]);
    $this->post($store, ['status' => 'sent', 'version' => 1])->assertRedirect($show);
    $this->post($store, ['status' => 'accepted', 'version' => 1])->assertSessionHasErrors('status');
    $this->post($store, ['status' => 'accepted', 'version' => 2])->assertRedirect($show);

    [$first, $accepted] = QuoteVersion::where('quote_id', $quote->id)->orderBy('number')->get()->all();
    expect($first->status)->toBe('superseded');
    expect($first->snapshot['subject'])->toBe('Original offer');
    expect($accepted->status)->toBe('accepted');
    expect($accepted->snapshot['subject'])->toBe('Revised offer');

    $quote->update(['subject' => 'Changed after acceptance']);
    $this->get($show)->assertOk()->assertSee('Revised offer');
    $this->get(route('admin.quotes.follow_up'))->assertOk()->assertDontSee('Revised offer');
    $this->get(route('admin.human_resources.contracts.create', ['quote_version_id' => $accepted->id]))->assertOk()->assertSee('Revised offer');

    $payload = [
        'quote_version_id' => $accepted->id, 'organization_id' => $company->id, 'contract_number' => 'TEST-'.fake()->uuid(),
        'title' => 'Revised offer', 'starts_at' => today()->toDateString(), 'monthly_value' => 45, 'status' => 'active',
    ];
    $this->post(route('admin.human_resources.contracts.store'), $payload)->assertRedirect(route('admin.human_resources.contracts.index'));
    expect(ServiceContract::where('quote_version_id', $accepted->id)->sole()->status)->toBe('draft');
    $this->post(route('admin.human_resources.contracts.store'), ['contract_number' => 'SECOND-'.fake()->uuid()] + $payload)->assertSessionHasErrors('quote_version_id');

    $scopedUser = User::create([
        'name' => 'Other quote editor', 'email' => fake()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => 'individual',
        'role_id' => Role::create(['name' => fake()->uuid(), 'description' => 'Test', 'permission_type' => 'custom', 'permissions' => ['quotes', 'quotes.edit', 'human-resources.contracts.create']])->id,
    ]);
    $this->actingAs($scopedUser, 'user')->get($show)->assertUnauthorized();
    $this->post($store, ['status' => 'sent', 'version' => 2])->assertUnauthorized();
    $this->get(route('admin.human_resources.contracts.create', ['quote_version_id' => $accepted->id]))->assertUnauthorized();
});
