<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['language', 'country']);

        return response()->json([
            'message' => 'Utilisateur inscrit avec succès. Veuillez vérifier votre adresse email.',
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Connexion d'un utilisateur et génération de token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Les identifiants fournis sont incorrects.',
            ], 401);
        }

        $user = User::where('email', $request->validated('email'))->firstOrFail();

        $deviceName = $request->validated('device_name') ?? 'auth_token';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['language', 'country']);

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Informations de l'utilisateur connecté.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['language', 'country']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Déconnexion (révocation du jeton actuel).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Déconnexion de tous les appareils (révocation de tous les jetons).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion de tous les appareils réussie.',
        ]);
    }
}
