<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Services\EmailVerificationCodeService;
use App\Services\PasswordResetCodeService;
use Illuminate\Auth\Events\Verified;

class AuthController extends Controller
{
    public function __construct(
        private TokenService $tokenService,
        private EmailVerificationCodeService $emailVerificationCodeService,
        private PasswordResetCodeService $passwordResetCodeService
    ) {}

    /**
     * Inscription
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'confirmed', PasswordRule::defaults()],
            'language_id'  => ['required', 'integer', 'exists:languages,id'],
            'country_id'   => ['required', 'integer', 'exists:countries,id'],
            'device_name'  => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'first_name'  => $validated['first_name'],
            'last_name'   => $validated['last_name'],
            'email'       => $validated['email'],
            'password'    => $validated['password'],
            'language_id' => $validated['language_id'],
            'country_id'  => $validated['country_id'],
        ]);

        // Déclenche l'envoi de l'email de vérification
        //event(new Registered($user));

        $this->emailVerificationCodeService->send($user);
        $tokens = $this->tokenService->createTokenPair($user, $validated['device_name']);

        return response()->json([
            'message' => __('auth.register_success'),
            'user'    => new UserResource($user),
            ...$tokens,
        ], 201);
    }

    /**
     * Connexion
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        if (!Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Révoque les anciens tokens de cet appareil
        $user->tokens()
            ->where('name', $validated['device_name'])
            ->orWhere('name', $validated['device_name'] . ' - Refresh')
            ->delete();

        $tokens = $this->tokenService->createTokenPair($user, $validated['device_name']);

        return response()->json([
            'message' => __('auth.login_success'),
            'user'    => new UserResource($user),
            ...$tokens,
        ]);
    }



    /**
     * Récupérer l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => new UserResource($user)]);
    }

    /**
     * Déconnexion (révoque uniquement le token actuel)
     */
    public function logout(Request $request): JsonResponse
    {
        // Révoque uniquement le token qui a servi à faire cette requête
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('auth.logout_success'),
        ]);
    }




    /**
     * Rafraîchir les tokens (Access + Refresh)
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // On vérifie que le token actuel a bien l'ability de refresh
        if (!$user->tokenCan(\App\Enums\TokenAbility::ISSUE_ACCESS_TOKEN->value)) {
            return response()->json([
                'message' => __('auth.invalid_refresh_token'),
            ], 401);
        }

        // Rotation des tokens
        $tokens = $this->tokenService->rotateTokens($user, $request->device_name);

        return response()->json([
            'message' => __('auth.token_refreshed'),
            ...$tokens,
        ]);
    }


    /**
     * Envoyer (ou renvoyer) l'email de vérification
     */
    public function sendVerificationEmailOld(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('auth.email_already_verified'),
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => __('auth.verification_link_sent'),
        ]);
    }

    /**
     * Vérifier l'email via le lien
     */
    public function verifyEmail(EmailVerificationRequest $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('auth.email_already_verified'),
            ]);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return response()->json([
            'message' => __('auth.email_verified'),
        ]);
    }



    /**
     * Demander un lien de réinitialisation de mot de passe
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // $status = Password::sendResetLink(
        //     $request->only('email')
        // );

        $user = \App\Models\User::where('email', $request->email)->first();

    // Toujours renvoyer le même message (sécurité : ne pas révéler si l'email existe)
    if ($user) {
        $this->passwordResetCodeService->send($user);
    }

        // if ($status === Password::RESET_LINK_SENT) {
        //     return response()->json([
        //         'message' => __('auth.reset_link_sent'),
        //     ]);
        // }

        // return response()->json([
        //     'message' => __('auth.reset_link_failed'),
        // ], 400);

        return response()->json([
        'message' => __('auth.reset_link_sent'),
    ]);
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => $request->password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Révoquer TOUS les tokens de l'utilisateur (sécurité)
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __('auth.password_reset_success'),
            ]);
        }

        return response()->json([
            'message' => __('auth.password_reset_failed'),
        ], 400);
    }


    /**
     * Lister les appareils / sessions de l'utilisateur
     */
    public function devices(Request $request): JsonResponse
    {

        $tokens = $request->user()->tokens()
            ->where('name', 'not like', '% - Refresh') // On masque les refresh tokens
            ->get()
            ->map(function ($token) use ($request) {
                return [
                    'id'         => $token->id,
                    'device_name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === $request->user()->currentAccessToken()->id,
                ];
            });

        return response()->json([
            'devices' => $tokens,
        ]);
    }

    /**
     * Révoquer un appareil spécifique
     */
    public function revokeDevice(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->json([
                'message' => __('auth.device_not_found'),
            ], 404);
        }

        // On supprime aussi le refresh token lié à cet appareil
        $request->user()->tokens()
            ->where('name', $token->name)
            ->orWhere('name', $token->name . ' - Refresh')
            ->delete();

        return response()->json([
            'message' => __('auth.device_revoked'),
        ]);
    }

    /**
     * Révoquer tous les autres appareils (sauf l'appareil actuel)
     */
    public function revokeOtherDevices(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $request->user()->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return response()->json([
            'message' => __('auth.other_devices_revoked'),
        ]);
    }


    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'first_name'  => ['sometimes', 'string', 'max:255'],
            'last_name'   => ['sometimes', 'string', 'max:255'],
            'email'       => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'language_id' => ['sometimes', 'integer', 'exists:languages,id'],
            'country_id'  => ['sometimes', 'integer', 'exists:countries,id'],
        ]);

        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;

        $user->fill($validated);

        // Si l'email a changé → on force une nouvelle vérification
        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();

            // Envoie automatiquement le nouvel email de vérification
            $user->sendEmailVerificationNotification();
        } else {
            $user->save();
        }

        return response()->json([
            'message' => $emailChanged
                ? __('auth.profile_updated_email_verification_sent')
                : __('auth.profile_updated'),
            'user' => new \App\Http\Resources\Api\V1\UserResource($user->fresh()),
        ]);
    }


    /**
     * Changer le mot de passe
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->update([
            'password' => $validated['password'],
        ]);

        // Option de sécurité : révoquer tous les autres tokens
        // (on garde uniquement le token actuel)
        $currentTokenId = $user->currentAccessToken()->id;

        $user->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return response()->json([
            'message' => __('auth.password_updated'),
        ]);
    }


    /**
     * Supprimer le compte de l'utilisateur (soft delete)
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Révoque tous les tokens
        $user->tokens()->delete();

        // Soft delete
        $user->delete();

        return response()->json([
            'message' => __('auth.account_deleted'),
        ]);
    }


    /**
     * Vérifier l'email via le lien signé
     */
    public function verifyEmailByLink(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        // Vérifie que le hash correspond bien à l'email
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => __('auth.invalid_verification_link'),
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('auth.email_already_verified'),
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => __('auth.email_verified'),
        ]);
    }







    /**
     * Renvoyer le code de vérification (et le lien classique aussi si besoin)
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('auth.email_already_verified'),
            ], 400);
        }

        // Un seul email
        $this->emailVerificationCodeService->send($user);

        return response()->json([
            'message' => __('auth.verification_code_sent'),
        ]);
    }

    /**
     * Vérifier l'email avec le code à 6 chiffres
     */
    public function verifyEmailWithCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'code'        => ['required', 'string', 'size:6'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => __('auth.user_not_found'),
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('auth.email_already_verified'),
            ], 400);
        }

        if (!$this->emailVerificationCodeService->verify($user, $validated['code'])) {
            return response()->json([
                'message' => __('auth.invalid_verification_code'),
            ], 422);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        // Connexion automatique
        $tokens = $this->tokenService->createTokenPair($user, $validated['device_name']);

        return response()->json([
            'message' => __('auth.email_verified_and_logged_in'),
            'user'    => new \App\Http\Resources\Api\V1\UserResource($user),
            ...$tokens,
        ]);
    }





    /**
 * Réinitialiser le mot de passe avec le code à 6 chiffres
 */
public function resetPasswordWithCode(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email'    => ['required', 'email'],
        'code'     => ['required', 'string', 'size:6'],
        'password' => ['required', 'confirmed', PasswordRule::defaults()],
    ]);

    $user = \App\Models\User::where('email', $validated['email'])->first();

    if (!$user) {
        return response()->json([
            'message' => __('auth.password_reset_failed'),
        ], 400);
    }

    if (!$this->passwordResetCodeService->verify($user, $validated['code'])) {
        return response()->json([
            'message' => __('auth.invalid_password_reset_code'),
        ], 422);
    }

    $user->forceFill([
        'password' => $validated['password'],
    ])->setRememberToken(\Illuminate\Support\Str::random(60));

    $user->save();

    // Révoque tous les tokens (sécurité)
    $user->tokens()->delete();

    event(new \Illuminate\Auth\Events\PasswordReset($user));

    return response()->json([
        'message' => __('auth.password_reset_success'),
    ]);
}
}


