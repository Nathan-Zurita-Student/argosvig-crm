<?php

namespace Webkul\Admin\Security;

use PragmaRX\Google2FA\Google2FA;
use Webkul\User\Models\User;

class TwoFactorAuthentication
{
    public const SESSION_KEY = 'admin.two_factor_verified';

    public function __construct(private Google2FA $authenticator) {}

    public function secret(): string
    {
        return $this->authenticator->generateSecretKey();
    }

    public function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->two_factor_session_version, config('app.key'));
    }

    public function step(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, ?int $lastStep = null): int|false
    {
        return preg_match('/\A[0-9]{6}\z/', $code) ? $this->authenticator->verifyKeyNewer($secret, $code, $lastStep ?? 0, 1) : false;
    }

    public function consume(User $user, #[\SensitiveParameter] string $code): bool
    {
        $step = $this->step($user->two_factor_secret, $code, $user->two_factor_last_step);

        if ($step !== false) {
            $user->two_factor_last_step = $step;
            $user->save();

            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $digest = hash('sha256', strtolower($code));

        foreach ($codes as $index => $candidate) {
            if (hash_equals($candidate, $digest)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = array_values($codes);
                $user->save();

                return true;
            }
        }

        return false;
    }
}
