<?php

namespace Webkul\Admin\Http\Controllers\User;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Security\TwoFactorAuthentication;
use Webkul\User\Models\UserProxy;

class TwoFactorController extends Controller
{
    private const SETUP_KEY = 'admin.two_factor_setup';

    public function index(Request $request): Response
    {
        return response()->view('admin::user.account.two-factor', [
            'enabled' => $request->user('user')->two_factor_secret !== null,
            'secret' => $this->pendingSetup($request)['secret'] ?? null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function begin(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password:user']]);
        $user = $request->user('user');
        abort_if($user->two_factor_secret !== null, 409);

        $request->session()->put(self::SETUP_KEY, [
            'secret' => $twoFactor->secret(),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'fingerprint' => $twoFactor->fingerprint($user),
            'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
        ]);

        return redirect()->route('admin.user.account.two_factor');
    }

    public function enable(Request $request, TwoFactorAuthentication $twoFactor): Response
    {
        $code = $request->validate(['verification_code' => ['required', 'string', 'digits:6']])['verification_code'];
        $pending = $this->pendingSetup($request);
        abort_unless($pending, 409);

        $codes = DB::transaction(function () use ($request, $twoFactor, $code, $pending) {
            $user = UserProxy::query()->lockForUpdate()->findOrFail($request->user('user')->id);
            abort_if($user->two_factor_secret !== null
                || ! hash_equals($pending['fingerprint'], $twoFactor->fingerprint($user))
                || ! hash_equals($pending['password_fingerprint'], hash('sha256', $user->getAuthPassword())), 409);
            $step = $twoFactor->step($pending['secret'], $code);

            if ($step === false) {
                throw ValidationException::withMessages(['verification_code' => trans('admin::two_factor.invalid')]);
            }

            $codes = array_map(fn () => bin2hex(random_bytes(10)), range(1, 8));
            $user->two_factor_secret = $pending['secret'];
            $user->two_factor_last_step = $step;
            $user->two_factor_recovery_codes = array_map(fn ($value) => hash('sha256', $value), $codes);
            $user->two_factor_session_version = bin2hex(random_bytes(20));
            $user->setRememberToken(bin2hex(random_bytes(30)));
            $user->save();

            $request->session()->forget(self::SETUP_KEY);
            $request->session()->regenerate(true);
            $request->session()->put(TwoFactorAuthentication::SESSION_KEY, $twoFactor->fingerprint($user));

            return $codes;
        });

        return response()->view('admin::user.account.two-factor', ['enabled' => true, 'secret' => null, 'recoveryCodes' => $codes])
            ->header('Cache-Control', 'private, no-store');
    }

    public function disable(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'current_password:user'], 'verification_code' => ['required', 'string', 'max:32']]);
        $actor = $request->user('user');
        $key = 'admin-two-factor:'.$actor->id;

        DB::transaction(function () use ($actor, $data, $twoFactor, $key) {
            $user = UserProxy::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->two_factor_secret !== null && hash_equals($twoFactor->fingerprint($actor), $twoFactor->fingerprint($user)), 409);
            abort_if(RateLimiter::tooManyAttempts($key, 5), 429);

            if (! $twoFactor->consume($user, $data['verification_code'])) {
                RateLimiter::hit($key, 300);

                throw ValidationException::withMessages(['verification_code' => trans('admin::two_factor.invalid')]);
            }

            $user->two_factor_secret = null;
            $user->two_factor_last_step = null;
            $user->two_factor_recovery_codes = null;
            $user->two_factor_session_version = bin2hex(random_bytes(20));
            $user->setRememberToken(bin2hex(random_bytes(30)));
            $user->save();
        });

        RateLimiter::clear($key);
        auth()->guard('user')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.session.create')->with('success', trans('admin::two_factor.disabled'));
    }

    private function pendingSetup(Request $request): ?array
    {
        $pending = $request->session()->get(self::SETUP_KEY);

        return $pending && $pending['expires_at'] > now()->timestamp ? $pending : null;
    }
}
