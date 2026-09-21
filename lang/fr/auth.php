<?php

// return [
//     'register_success' => 'Inscription réussie. Veuillez vérifier votre adresse email.',
//     'login_success'    => 'Connexion réussie.',
//     'failed'           => 'Les identifiants fournis sont incorrects.',
//     'logout_success'   => 'Déconnexion réussie.',
//     'token_refreshed'       => 'Tokens rafraîchis avec succès.',
//     'invalid_refresh_token' => 'Refresh token invalide.',
//     'email_already_verified'   => 'Votre adresse email est déjà vérifiée.',
//     'verification_link_sent'   => 'Lien de vérification envoyé.',
//     'email_verified'           => 'Adresse email vérifiée avec succès.',
//     'reset_link_sent'        => 'Lien de réinitialisation envoyé par email.',
//     'reset_link_failed'      => 'Impossible d\'envoyer le lien de réinitialisation.',
//     'password_reset_success' => 'Mot de passe réinitialisé avec succès.',
//     'password_reset_failed'  => 'Échec de la réinitialisation du mot de passe.',
//     'device_not_found'       => 'Appareil introuvable.',
//     'device_revoked'         => 'Appareil révoqué avec succès.',
//     'other_devices_revoked'  => 'Tous les autres appareils ont été déconnectés.',
//     'profile_updated'                        => 'Profil mis à jour avec succès.',
//     'profile_updated_email_verification_sent' => 'Profil mis à jour. Un email de vérification a été envoyé à votre nouvelle adresse.',
//     'password_updated' => 'Mot de passe modifié avec succès.',
//     'account_deleted' => 'Votre compte a été supprimé avec succès.',
//     'invalid_verification_link' => 'Lien de vérification invalide.',
//     'verification_code_subject'  => 'Votre code de vérification',
//     'verification_code_line'     => 'Voici votre code de vérification :',
//     'verification_code_expires'  => 'Ce code expire dans :minutes minutes.',
//     'verification_code_sent'     => 'Code de vérification envoyé.',
//     'invalid_verification_code'  => 'Code de vérification invalide ou expiré.',
//     'or_click_the_link'     => 'Ou cliquez sur le bouton ci-dessous :',
//     'verify_email_button'   => 'Vérifier mon email',
//     'password_reset_code_subject'  => 'Code de réinitialisation de mot de passe',
//     'password_reset_code_line'     => 'Voici votre code de réinitialisation :',
//     'password_reset_code_expires'  => 'Ce code expire dans :minutes minutes.',
//     'invalid_password_reset_code'  => 'Code de réinitialisation invalide ou expiré.',
// ];


return [

    /*
    |--------------------------------------------------------------------------
    | Authentification
    |--------------------------------------------------------------------------
    */

    'failed'   => 'Les identifiants fournis sont incorrects.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',

    // Messages généraux
    'register_success'   => 'Inscription réussie. Veuillez vérifier votre adresse email.',
    'login_success'      => 'Connexion réussie.',
    'logout_success'     => 'Déconnexion réussie.',
    'unauthenticated'    => 'Non authentifié.',
    'user_not_found'     => 'Utilisateur introuvable.',

    // Tokens
    'token_refreshed'        => 'Tokens rafraîchis avec succès.',
    'invalid_refresh_token'  => 'Refresh token invalide.',

    // Email verification
    'email_already_verified'             => 'Votre adresse email est déjà vérifiée.',
    'verification_link_sent'             => 'Lien de vérification envoyé.',
    'email_verified'                     => 'Adresse email vérifiée avec succès.',
    'email_verified_and_logged_in'       => 'Email vérifié. Vous êtes maintenant connecté.',
    'invalid_verification_link'          => 'Lien de vérification invalide.',
    'invalid_or_expired_code'            => 'Code invalide ou expiré.',
    'invalid_verification_code'          => 'Code de vérification invalide ou expiré.',
    'verification_code_sent'             => 'Code de vérification envoyé.',

    // Contenu de l'email de vérification
    'verification_code_subject'  => 'Votre code de vérification',
    'verification_code_line'     => 'Voici votre code de vérification :',
    'verification_code_expires'  => 'Ce code expire dans :minutes minutes.',
    'or_click_the_link'          => 'Ou cliquez sur le bouton ci-dessous :',
    'verify_email_button'        => 'Vérifier mon email',

    // Reset password
    'reset_link_sent'              => 'Si un compte existe avec cet email, un code de réinitialisation a été envoyé.',
    'reset_link_failed'            => 'Impossible d\'envoyer le lien de réinitialisation.',
    'password_reset_success'       => 'Mot de passe réinitialisé avec succès.',
    'password_reset_failed'        => 'Échec de la réinitialisation du mot de passe.',
    'invalid_password_reset_code'  => 'Code de réinitialisation invalide ou expiré.',

    // Contenu de l'email de reset password
    'password_reset_code_subject'  => 'Code de réinitialisation de mot de passe',
    'password_reset_code_line'     => 'Voici votre code de réinitialisation :',
    'password_reset_code_expires'  => 'Ce code expire dans :minutes minutes.',

    // Profil
    'profile_updated'                         => 'Profil mis à jour avec succès.',
    'profile_updated_email_verification_sent' => 'Profil mis à jour. Un email de vérification a été envoyé à votre nouvelle adresse.',
    'password_updated'                        => 'Mot de passe modifié avec succès.',
    'account_deleted'                         => 'Votre compte a été supprimé avec succès.',

    // Devices
    'device_not_found'        => 'Appareil introuvable.',
    'device_revoked'          => 'Appareil révoqué avec succès.',
    'other_devices_revoked'   => 'Tous les autres appareils ont été déconnectés.',
];