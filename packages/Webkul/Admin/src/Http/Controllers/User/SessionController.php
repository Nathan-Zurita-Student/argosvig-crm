<?php

namespace Webkul\Admin\Http\Controllers\User;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Security\TwoFactorAuthentication;
use Webkul\Core\Menu\MenuItem;
use Webkul\User\Models\UserProxy;

class SessionController extends Controller
{
    /**
     * Number of failed attempts allowed per minute, per set of credentials and per address.
     */
    private const MAX_ATTEMPTS_PER_CREDENTIAL = 5;

    private const MAX_ATTEMPTS_PER_ADDRESS = 20;

    private const PENDING_KEY = 'admin.two_factor_pending';

    /**
     * Show the form for creating a new resource.
     */
    public function create(): RedirectResponse|View
    {
        if (auth()->guard('user')->check()) {
            return redirect()->route('admin.dashboard.index');
        }

        $previousUrl = url()->previous();

        $intendedUrl = str_contains($previousUrl, 'admin')
            ? $previousUrl
            : route('admin.dashboard.index');

        session()->put('url.intended', $intendedUrl);

        return view('admin::sessions.login');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $this->validate(request(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $this->ensureIsNotRateLimited();

        session()->forget(self::PENDING_KEY);
        $guard = auth()->guard('user');
        $credentials = request(['email', 'password']);

        if (! $guard->validate($credentials)) {
            $this->recordFailedAttempt();

            session()->flash('error', trans('admin::app.users.login-error'));

            return redirect()->back();
        }

        $this->clearRateLimit();

        $user = $guard->getLastAttempted();

        if (! $user->status) {
            session()->flash('warning', trans('admin::app.users.activate-warning'));

            $guard->logout();

            return redirect()->route('admin.session.create');
        }

        $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);

        if ($user->two_factor_secret !== null) {
            session()->regenerate(true);
            session()->put(self::PENDING_KEY, [
                'user_id' => $user->id,
                'fingerprint' => $twoFactor->fingerprint($user),
                'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('admin.session.two_factor');
        }

        $guard->login($user, $user->two_factor_session_version === null && request()->boolean('remember'));
        session()->put(TwoFactorAuthentication::SESSION_KEY, $twoFactor->fingerprint($user));

        return $this->afterLogin();
    }

    public function twoFactor(): RedirectResponse|Response
    {
        if (! $this->pendingChallenge()) {
            return redirect()->route('admin.session.create');
        }

        return response()->view('admin::sessions.two-factor')->header('Cache-Control', 'private, no-store');
    }

    public function verifyTwoFactor(TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        if (! $pending = $this->pendingChallenge()) {
            return redirect()->route('admin.session.create');
        }

        $code = request()->validate(['verification_code' => ['required', 'string', 'max:32']])['verification_code'];
        $key = 'admin-two-factor:'.$pending['user_id'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429);
        }

        $user = DB::transaction(function () use ($pending, $key, $code, $twoFactor) {
            $user = UserProxy::query()->whereKey($pending['user_id'])->where('status', true)->lockForUpdate()->first();

            if (! $user || $user->two_factor_secret === null
                || ! hash_equals($pending['fingerprint'], $twoFactor->fingerprint($user))
                || ! hash_equals($pending['password_fingerprint'], hash('sha256', $user->getAuthPassword()))) {
                session()->forget(self::PENDING_KEY);

                return null;
            }

            if (RateLimiter::tooManyAttempts($key, 5)) {
                abort(429);
            }

            if (! $twoFactor->consume($user, $code)) {
                RateLimiter::hit($key, 300);

                return null;
            }

            return $user;
        });

        if (! $user) {
            return back()->withErrors(['verification_code' => trans('admin::two_factor.invalid')]);
        }

        RateLimiter::clear($key);
        session()->forget(self::PENDING_KEY);
        auth()->guard('user')->login($user);
        session()->put(TwoFactorAuthentication::SESSION_KEY, $twoFactor->fingerprint($user));

        return $this->afterLogin();
    }

    private function pendingChallenge(): ?array
    {
        $pending = session()->get(self::PENDING_KEY);

        if (! $pending || now()->timestamp >= $pending['expires_at']) {
            session()->forget(self::PENDING_KEY);

            return null;
        }

        return $pending;
    }

    private function afterLogin(): RedirectResponse
    {

        $menus = menu()->getItems('admin');

        $availableNextMenu = $menus?->first();

        if (! bouncer()->hasPermission('dashboard')) {
            if (is_null($availableNextMenu)) {
                session()->flash('error', trans('admin::app.users.not-permission'));

                auth()->guard('user')->logout();

                return redirect()->route('admin.session.create');
            }

            return redirect()->to($availableNextMenu->getUrl());
        }

        $hasAccessToIntendedUrl = $this->canAccessIntendedUrl($menus, redirect()->getIntendedUrl());

        if ($hasAccessToIntendedUrl) {
            return redirect()->intended(route('admin.dashboard.index'));
        }

        return redirect()->to($availableNextMenu->getUrl());
    }

    /**
     * Stop the request when too many recent attempts have already failed.
     *
     * Only failures are counted. Throttling every attempt would also throttle people who sign in
     * correctly — a browser test suite signing in for each of its cases, or several staff behind one
     * office address — while doing nothing extra against password guessing, which is made up
     * entirely of failures.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (
            RateLimiter::tooManyAttempts($this->credentialRateLimitKey(), self::MAX_ATTEMPTS_PER_CREDENTIAL)
            || RateLimiter::tooManyAttempts($this->addressRateLimitKey(), self::MAX_ATTEMPTS_PER_ADDRESS)
        ) {
            abort(429);
        }
    }

    /**
     * Count a failed attempt against both the credentials used and the address it came from, so
     * neither guessing one password nor spraying many accounts from one place goes unchecked.
     */
    protected function recordFailedAttempt(): void
    {
        RateLimiter::hit($this->credentialRateLimitKey());

        RateLimiter::hit($this->addressRateLimitKey());
    }

    /**
     * A successful sign-in clears the record for those credentials.
     */
    protected function clearRateLimit(): void
    {
        RateLimiter::clear($this->credentialRateLimitKey());
    }

    protected function credentialRateLimitKey(): string
    {
        return 'admin-login|'.hash('sha256', Str::lower((string) request('email')).'|'.request()->ip());
    }

    protected function addressRateLimitKey(): string
    {
        return 'admin-login-address|'.hash('sha256', (string) request()->ip());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(): RedirectResponse
    {
        auth()->guard('user')->logout();

        /**
         * Drop every value the session carried and rotate its identifier, so no
         * data survives the logout and the id cannot be replayed afterwards.
         */
        session()->invalidate();

        session()->regenerateToken();

        return redirect()->route('admin.session.create');
    }

    /**
     * Find menu item by URL.
     */
    protected function canAccessIntendedUrl(Collection $menus, ?string $url): ?MenuItem
    {
        if (is_null($url)) {
            return null;
        }

        foreach ($menus as $menu) {
            if ($menu->getUrl() === $url) {
                return $menu;
            }

            if ($menu->haveChildren()) {
                $found = $this->canAccessIntendedUrl($menu->getChildren(), $url);

                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
