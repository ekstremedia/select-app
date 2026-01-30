<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Domain\Player\Actions\ConvertGuestToUserAction;
use App\Domain\Player\Actions\CreateGuestPlayerAction;
use App\Domain\Player\Actions\GetPlayerByTokenAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function guest(Request $request, CreateGuestPlayerAction $action): JsonResponse
    {
        $request->validate([
            'display_name' => 'required|string|min:2|max:50',
        ]);

        $player = $action->execute($request->display_name);

        return response()->json([
            'player' => [
                'id' => $player->id,
                'display_name' => $player->display_name,
                'guest_token' => $player->guest_token,
                'is_guest' => true,
            ],
        ], 201);
    }

    public function register(Request $request, ConvertGuestToUserAction $convertAction): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'name' => 'nullable|string|max:255',
            'guest_token' => 'nullable|string',
        ]);

        // If guest token provided, convert guest to user
        if ($request->guest_token) {
            $player = Player::where('guest_token', $request->guest_token)->first();
            if ($player) {
                $player = $convertAction->execute(
                    $player,
                    $request->email,
                    $request->password,
                    $request->name
                );

                $token = $player->user->createToken('api')->plainTextToken;

                return response()->json([
                    'player' => [
                        'id' => $player->id,
                        'display_name' => $player->display_name,
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
        $user = User::create([
            'name' => $request->name ?? explode('@', $request->email)[0],
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $player = Player::create([
            'user_id' => $user->id,
            'display_name' => $request->name ?? explode('@', $request->email)[0],
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'player' => [
                'id' => $player->id,
                'display_name' => $player->display_name,
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

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $player = Player::where('user_id', $user->id)->first();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'player' => $player ? [
                'id' => $player->id,
                'display_name' => $player->display_name,
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

    public function convert(Request $request, ConvertGuestToUserAction $action): JsonResponse
    {
        $request->validate([
            'guest_token' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'name' => 'nullable|string|max:255',
        ]);

        $player = Player::where('guest_token', $request->guest_token)->first();

        if (!$player) {
            return response()->json(['error' => 'Invalid guest token'], 404);
        }

        $player = $action->execute(
            $player,
            $request->email,
            $request->password,
            $request->name
        );

        $token = $player->user->createToken('api')->plainTextToken;

        return response()->json([
            'player' => [
                'id' => $player->id,
                'display_name' => $player->display_name,
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

    public function me(Request $request, GetPlayerByTokenAction $getPlayerAction): JsonResponse
    {
        // Check for guest token header
        $guestToken = $request->header('X-Guest-Token');
        if ($guestToken) {
            $player = $getPlayerAction->execute($guestToken);
            if ($player) {
                return response()->json([
                    'player' => [
                        'id' => $player->id,
                        'display_name' => $player->display_name,
                        'is_guest' => true,
                        'stats' => [
                            'games_played' => $player->games_played,
                            'games_won' => $player->games_won,
                            'total_score' => $player->total_score,
                        ],
                    ],
                ]);
            }
        }

        // Check for authenticated user
        if ($request->user()) {
            $player = Player::where('user_id', $request->user()->id)->first();
            return response()->json([
                'player' => $player ? [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'is_guest' => false,
                    'stats' => [
                        'games_played' => $player->games_played,
                        'games_won' => $player->games_won,
                        'total_score' => $player->total_score,
                    ],
                ] : null,
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ]);
        }

        return response()->json(['error' => 'Unauthenticated'], 401);
    }
}
