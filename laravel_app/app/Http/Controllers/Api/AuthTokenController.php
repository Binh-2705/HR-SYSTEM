<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthTokenController extends Controller
{
    public function issue(IssueTokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (!$user || !Hash::check((string) $request->input('password'), (string) $user->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user->createToken(
            $request->input('device_name', 'api-client')
        );

        return response()->json([
            'ok' => true,
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Token revoked.',
        ]);
    }
}
