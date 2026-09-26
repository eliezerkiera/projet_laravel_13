<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lignes de langue d'authentification (Français)
    |--------------------------------------------------------------------------
    */

    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Tentatives de connexion trop nombreuses. Veuillez réessayer dans :seconds secondes.',

    // OTP & Vérification
    'otp_sent' => 'Un code de vérification a été envoyé à votre adresse email.',
    'otp_invalid' => 'Le code de vérification est invalide.',
    'otp_expired' => 'Le code de vérification a expiré. Veuillez en demander un nouveau.',
    'otp_max_attempts' => 'Nombre maximal de tentatives dépassé. Veuillez demander un nouveau code.',
    'otp_wait_resend' => 'Veuillez patienter :seconds secondes avant de demander un nouveau code.',
    'invalid_token' => 'Le jeton de vérification est invalide ou a expiré.',

    // Inscription
    'registration_completed' => 'Votre compte a été enregistré avec succès.',

    // Connexion & 2FA
    'login_otp_required' => 'Identifiants vérifiés. Veuillez saisir le code de vérification envoyé à votre email.',
    'login_success' => 'Connexion réussie.',
    'pending_email_verification_required' => 'Vous devez valider votre nouvelle adresse email avant de pouvoir vous connecter.',

    // Réinitialisation mot de passe
    'password_reset_code_verified' => 'Code de vérification confirmé. Vous pouvez maintenant réinitialiser votre mot de passe.',
    'password_reset_success' => 'Votre mot de passe a été réinitialisé avec succès.',

    // Profil & Mot de passe
    'profile_updated' => 'Profil mis à jour avec succès.',
    'email_change_otp_sent' => 'Profil mis à jour. Un code de vérification a été envoyé à votre nouvelle adresse email.',
    'email_verified_success' => 'Adresse email vérifiée et mise à jour avec succès.',
    'password_changed' => 'Mot de passe modifié avec succès.',

    // Déconnexion & Suppression de compte
    'logged_out' => 'Déconnexion réussie.',
    'account_deleted' => 'Votre compte a été supprimé avec succès.',

    // Gestion des appareils
    'device_disconnected' => 'Appareil déconnecté avec succès.',
    'other_devices_disconnected' => 'Tous les autres appareils ont été déconnectés avec succès.',
    'device_not_found' => 'Session d\'appareil introuvable.',

    // Refresh Token
    'token_refreshed' => 'Jeton rafraîchi avec succès.',
    'invalid_refresh_token' => 'Le jeton de rafraîchissement est invalide.',
    'refresh_token_expired' => 'Le jeton de rafraîchissement a expiré. Veuillez vous reconnecter.',

    // Notifications email
    'mail' => [
        'subject' => 'Votre code de vérification : :code',
        'greeting' => 'Bonjour :name,',
        'greeting_generic' => 'Bonjour,',
        'body' => 'Votre code de vérification est :',
        'expiry' => 'Ce code expirera dans :minutes minutes.',
        'warning' => 'Si vous n\'avez pas initié cette demande, aucune action n\'est requise.',
    ],

];
