<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class InactiveTokenResponse
{
    public static function revoke(User $user, string $code = 'COMPANY_INACTIVE'): void
    {
        foreach ($user->tokens()->get() as $token) {
            // Only the stored SHA-256 digest is retained, never the bearer secret.
            Cache::store('file')->put('inactive-token:'.$token->token, $code, now()->addDays(7));
        }
        $user->tokens()->delete();
    }

    public static function code(?string $bearer): ?string
    {
        if (!$bearer) return null;
        $secret = str_contains($bearer, '|') ? explode('|', $bearer, 2)[1] : $bearer;
        $code = Cache::store('file')->get('inactive-token:'.hash('sha256', $secret));
        return in_array($code, ['COMPANY_INACTIVE', 'USER_INACTIVE'], true) ? $code : null;
    }
}
