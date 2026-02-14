<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Http\Requests\Api\V1\TwoFactorConfirmRequest;
use App\Application\Http\Requests\Api\V1\TwoFactorDisableRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['error' => 'Two-factor authentication is already enabled.'], 422);
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => json_encode(
                Collection::times(8, fn () => Str::random(10))->all()
            ),
        ]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => json_decode($user->two_factor_recovery_codes, true),
        ]);
    }

    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['error' => 'Two-factor authentication has not been enabled.'], 422);
        }

        if ($user->two_factor_confirmed_at) {
            return response()->json(['error' => 'Two-factor authentication is already confirmed.'], 422);
        }

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->validated('code'));

        if (! $valid) {
            return response()->json(['error' => 'The provided code is invalid.'], 422);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json(['message' => 'Two-factor authentication confirmed.']);
    }

    public function disable(TwoFactorDisableRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['error' => 'The provided password is incorrect.'], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }
}
