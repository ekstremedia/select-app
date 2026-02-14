<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Http\Requests\Api\V1\ConvertGuestRequest;
use App\Application\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Application\Http\Requests\Api\V1\GuestRequest;
use App\Application\Http\Requests\Api\V1\LoginRequest;
use App\Application\Http\Requests\Api\V1\RegisterRequest;
use App\Application\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Domain\Player\Actions\ConvertGuestToUserAction;
use App\Domain\Player\Actions\CreateGuestPlayerAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function guest(GuestRequest $request, CreateGuestPlayerAction $action): JsonResponse
    {
        $player = $action->execute($request->validated('nickname'));

        return response()->json([
            'player' => [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'guest_token' => $player->guest_token,
                'is_guest' => true,
            ],
        ], 201);
    }

    public function register(RegisterRequest $request, ConvertGuestToUserAction $convertAction): JsonResponse
    {
        $validated = $request->validated();

        // If guest token provided, convert guest to user
        if (! empty($validated['guest_token'])) {
            $player = Player::where('guest_token', $validated['guest_token'])->first();
            if ($player) {
                $player = $convertAction->execute(
                    $player,
                    $validated['email'],
                    $validated['password'],
                    $validated['name'] ?? null,
                    $validated['nickname'] ?? null,
                );

                $token = $player->user->createToken('api')->plainTextToken;

                return response()->json([
                    'player' => [
                        'id' => $player->id,
                        'nickname' => $player->nickname,
                        'is_guest' => false,
                    ],
                    'user' => [
                        'id' => $player->user->id,
                        'name' => $player->user->name,
                        'email' => $player->user->email,
                    ],
                    'token' => $token,
                ], 201);
            }
        }

        // Create new user and player
        $nickname = $validated['nickname'] ?? explode('@', $validated['email'])[0];

        $user = User::create([
            'name' => $validated['name'] ?? $nickname,
            'nickname' => $nickname,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $player = Player::create([
            'user_id' => $user->id,
            'nickname' => $nickname,
            'is_guest' => false,
            'last_active_at' => now(),
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'player' => [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'is_guest' => false,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is banned
        if ($user->isBanned()) {
            return response()->json([
                'error' => 'Your account has been banned.',
                'reason' => $user->ban_reason,
            ], 403);
        }

        // Check 2FA
        if ($user->hasTwoFactorEnabled()) {
            if (empty($validated['two_factor_code'])) {
                return response()->json([
                    'two_factor_required' => true,
                    'message' => 'Two-factor authentication code required.',
                ], 422);
            }

            $google2fa = new Google2FA;
            $valid = $google2fa->verifyKey($user->two_factor_secret, $validated['two_factor_code']);

            if (! $valid) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['The two-factor code is invalid.'],
                ]);
            }
        }

        $player = Player::where('user_id', $user->id)->first();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'player' => $player ? [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'is_guest' => false,
                'stats' => [
                    'games_played' => $player->games_played,
                    'games_won' => $player->games_won,
                    'total_score' => $player->total_score,
                ],
            ] : null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function convert(ConvertGuestRequest $request, ConvertGuestToUserAction $action): JsonResponse
    {
        $validated = $request->validated();

        $player = Player::where('guest_token', $validated['guest_token'])->first();

        if (! $player) {
            return response()->json(['error' => 'Invalid guest token'], 404);
        }

        $player = $action->execute(
            $player,
            $validated['email'],
            $validated['password'],
            $validated['name'] ?? null,
            $validated['nickname'] ?? null,
        );

        $token = $player->user->createToken('api')->plainTextToken;

        return response()->json([
            'player' => [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'is_guest' => false,
            ],
            'user' => [
                'id' => $player->user->id,
                'name' => $player->user->name,
                'email' => $player->user->email,
            ],
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');

        if (! $player) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $response = [
            'player' => [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'is_guest' => $player->isGuest(),
                'stats' => [
                    'games_played' => $player->games_played,
                    'games_won' => $player->games_won,
                    'total_score' => $player->total_score,
                ],
            ],
        ];

        if ($request->user()) {
            $response['user'] = [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ];
        }

        return response()->json($response);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Password reset link sent.']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password has been reset.']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
