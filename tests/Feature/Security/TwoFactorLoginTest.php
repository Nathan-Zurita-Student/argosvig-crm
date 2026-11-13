<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('enrolls an authenticator and accepts recovery codes only once after password validation', function () {
    $admin = User::create([
        'name' => 'MFA test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => 'global', 'role_id' => getDefaultAdmin()->role_id,
    ]);
    $login = route('admin.session.store');
    $challenge = route('admin.session.two_factor');
    $verify = route('admin.session.two_factor.verify');
    $credentials = ['email' => $admin->email, 'password' => 'test-secret'];

    $this->get($challenge)->assertRedirect(route('admin.session.create'));
    $this->actingAs($admin, 'user')->post(route('admin.user.account.two_factor.begin'), ['password' => 'test-secret'])->assertRedirect();
    $secret = session('admin.two_factor_setup.secret');
    expect($secret)->toBeString();
    $authenticator = new Google2FA;
    $initialCode = $authenticator->getCurrentOtp($secret);
    $response = $this->post(route('admin.user.account.two_factor.enable'), ['verification_code' => $initialCode])->assertOk();
    [$first, $second] = $response->viewData('recoveryCodes');

    $fresh = $admin->refresh();
    expect($fresh->getRawOriginal('two_factor_secret'))->not->toBe($secret);
    expect($fresh->toArray())->not->toHaveKey('two_factor_secret');
    $this->get(route('admin.dashboard.index'))->assertOk();

    $this->delete(route('admin.session.destroy'))->assertRedirect();
    Auth::forgetGuards();
    $this->post($login, $credentials)->assertRedirect($challenge);
    $this->assertGuest('user');
    $this->get(route('admin.dashboard.index'))->assertRedirect(route('admin.session.create'));
    $this->get($challenge)->assertOk();
    $this->from($challenge)->post($verify, ['verification_code' => $initialCode])->assertSessionHasErrors('verification_code');
    $this->assertGuest('user');
    $this->post($verify, ['verification_code' => $first])->assertRedirect();
    $this->assertAuthenticatedAs($admin, 'user');
    $this->get(route('admin.dashboard.index'))->assertOk();

    $this->delete(route('admin.session.destroy'))->assertRedirect();
    Auth::forgetGuards();
    $this->post($login, $credentials)->assertRedirect($challenge);
    $this->from($challenge)->post($verify, ['verification_code' => $first])->assertSessionHasErrors('verification_code');
    $this->assertGuest('user');
    $this->post($verify, ['verification_code' => $second])->assertRedirect();
    $this->assertAuthenticatedAs($admin, 'user');
});

it('rejects a session with no second factor for a previously enrolled account', function () {
    $admin = getDefaultAdmin();
    $admin->two_factor_session_version = bin2hex(random_bytes(20));
    $admin->save();

    $this->actingAs($admin, 'user')->get(route('admin.dashboard.index'))->assertRedirect(route('admin.session.create'));
    $this->assertGuest('user');
});
