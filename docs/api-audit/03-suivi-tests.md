# Suivi des Tests Insomnia - API v2

**Date :** 2026-10-10  
**Objectif :** Suivi de l'exécution des tests manuels Insomnia

---

## Tableau de Suivi

| ID | Endpoint | Scénario | HTTP Attendu | HTTP Obtenu | Conforme | Éléments vérifiés | Notes |
|----|----------|----------|--------------|-------------|----------|-------------------|-------|
| TEST-001 | GET /locale | Contexte initial | 200 | - | - | - | À exécuter |
| TEST-002 | GET /countries/active | Liste pays actifs | 200 | - | - | - | À exécuter |
| TEST-003 | POST /auth/register/send-code | Envoi code inscription | 200 | - | - | - | À exécuter |
| TEST-004 | POST /auth/register/verify-code | Vérification code | 200 | - | - | - | À exécuter |
| TEST-005 | POST /auth/register/complete | Finalisation inscription | 201 | - | - | - | À exécuter |
| TEST-006 | POST /auth/login | Login étape 1 | 200 | - | - | - | À exécuter |
| TEST-007 | POST /auth/login/verify-code | Login étape 2 | 200 | - | - | - | À exécuter |
| TEST-008 | POST /auth/refresh | Refresh token | 200 | - | - | - | À exécuter |
| TEST-009 | GET /auth/me | Profil utilisateur | 200 | - | - | - | À exécuter |
| TEST-010 | PUT /auth/profile | Modification profil | 200 | - | - | - | À exécuter |
| TEST-011 | POST /auth/profile/verify-email | Vérification email | 200 | - | - | - | À exécuter |
| TEST-012 | PUT /auth/password | Changement mot de passe | 200 | - | - | - | À exécuter |
| TEST-013 | GET /auth/devices | Liste appareils | 200 | - | - | - | À exécuter |
| TEST-014 | DELETE /auth/devices/{id} | Déconnexion appareil | 200 | - | - | - | À exécuter |
| TEST-015 | DELETE /auth/devices | Déconnexion autres | 200 | - | - | - | À exécuter |
| TEST-016 | POST /auth/logout | Déconnexion | 200 | - | - | - | À exécuter |
| TEST-017 | PATCH /locale | Mise à jour locale | 200 | - | - | - | À exécuter |
| TEST-018 | DELETE /auth/account | Suppression compte | 200 | - | - | - | À exécuter |
| TEST-019 | POST /auth/forgot-password/send-code | Envoi code forgot | 200 | - | - | - | À exécuter |
| TEST-020 | POST /auth/forgot-password/verify-code | Vérification forgot | 200 | - | - | - | À exécuter |
| TEST-021 | POST /auth/forgot-password/reset | Réinitialisation mot de passe | 200 | - | - | - | À exécuter |
| TEST-022 | POST /auth/verify-pending-email | Email pending guest | 200 | - | - | - | À exécuter |
| TEST-023 | POST /auth/register/resend-code | Renvoi code register | 200 | - | - | - | À exécuter |
| TEST-024 | POST /auth/login/resend-code | Renvoi code login | 200 | - | - | - | À exécuter |
| TEST-025 | POST /auth/forgot-password/resend-code | Renvoi code forgot | 200 | - | - | - | À exécuter |
| TEST-026 | GET /auth/me (sans auth) | Sans authentification | 401 | - | - | - | À exécuter |
| TEST-027 | GET /auth/me (token invalide) | Token invalide | 401 | - | - | - | À exécuter |
| TEST-028 | POST /auth/register/send-code (sans email) | Champ manquant | 422 | - | - | - | À exécuter |
| TEST-029 | POST /auth/login (email invalide) | Type incorrect | 422 | - | - | - | À exécuter |
| TEST-030 | POST /auth/login (rate limit) | Rate limiting | 429 | - | - | - | À exécuter |
| TEST-031 | DELETE /auth/devices/{id} (inexistant) | Device non trouvé | 404 | - | - | - | À exécuter |
| TEST-032 | POST /auth/login/verify-code (code expiré) | Code OTP expiré | 422 | - | - | - | À exécuter |

---

## Statistiques

- **Total de tests :** 32
- **Tests exécutés :** 0
- **Tests conformes :** 0
- **Tests non conformes :** 0
- **Tests en attente :** 32

---

## Instructions de mise à jour

Pour chaque test exécuté, mettre à jour ce fichier avec :

1. **HTTP Obtenu** : Le code HTTP réel reçu
2. **Conforme** : Oui/Non selon si le résultat correspond à la documentation
3. **Éléments vérifiés** : Liste des champs contrôlés
4. **Notes** : Toute observation pertinente, écart détecté, ou problème rencontré

---

**Fin du fichier de suivi**
