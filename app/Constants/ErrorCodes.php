<?php

namespace App\Constants;

/**
 * Codes d'erreur métier Certus.
 * Utilisés dans CertusException et dans les réponses API { "meta": { "code_erreur": "..." } }.
 */
final class ErrorCodes
{
    // ----------------------------------------------------------------
    // Licence
    // ----------------------------------------------------------------
    const LICENCE_INTROUVABLE    = 'LICENCE_INTROUVABLE';
    const LICENCE_EXPIREE        = 'LICENCE_EXPIREE';
    const LICENCE_SUSPENDUE      = 'LICENCE_SUSPENDUE';
    const LICENCE_REVOQUEE       = 'LICENCE_REVOQUEE';
    const LICENCE_INVALIDE       = 'LICENCE_INVALIDE';
    const LICENCE_QUOTA_DEPASSE  = 'LICENCE_QUOTA_DEPASSE';

    // ----------------------------------------------------------------
    // Clé de licence
    // ----------------------------------------------------------------
    const CLE_INVALIDE           = 'CLE_INVALIDE';
    const CLE_FORMAT_INVALIDE    = 'CLE_FORMAT_INVALIDE';
    const CLE_SIGNATURE_INVALIDE = 'CLE_SIGNATURE_INVALIDE';
    const CLE_CRC_INVALIDE       = 'CLE_CRC_INVALIDE';

    // ----------------------------------------------------------------
    // Fingerprint machine
    // ----------------------------------------------------------------
    const FINGERPRINT_INVALIDE   = 'FINGERPRINT_INVALIDE';
    const FINGERPRINT_BLACKLISTE = 'FINGERPRINT_BLACKLISTE';

    // ----------------------------------------------------------------
    // Anti-rejeu (nonce)
    // ----------------------------------------------------------------
    const ANTIREJEU_INVALIDE     = 'ANTIREJEU_INVALIDE';
    const ANTIREJEU_REJOUE       = 'ANTIREJEU_REJOUE';

    // ----------------------------------------------------------------
    // Organisation
    // ----------------------------------------------------------------
    const ORGANISATION_INTROUVABLE = 'ORGANISATION_INTROUVABLE';
    const ORGANISATION_INACTIVE    = 'ORGANISATION_INACTIVE';

    // ----------------------------------------------------------------
    // Activation
    // ----------------------------------------------------------------
    const ACTIVATION_IMPOSSIBLE  = 'ACTIVATION_IMPOSSIBLE';
    const ACTIVATION_DEJA_ACTIVE = 'ACTIVATION_DEJA_ACTIVE';
    const ACTIVATION_INTROUVABLE = 'ACTIVATION_INTROUVABLE';

    // ----------------------------------------------------------------
    // Signaux de piratage (les 4 signaux Certus)
    // ----------------------------------------------------------------
    const QUOTA_POSTES_ATTEINT      = 'QUOTA_POSTES_ATTEINT';      // S1 : nb_activations_actives >= nb_postes
    const SIGNAL_RAFALE             = 'SIGNAL_RAFALE';              // S2 : > 5 activations en 1 heure (warn only)
    const FINGERPRINT_MULTI_LICENCES = 'FINGERPRINT_MULTI_LICENCES'; // S3 : même fingerprint sur 2+ licences
    const CLE_PARTAGEE_DETECTEE     = 'CLE_PARTAGEE_DETECTEE';     // S4 : anti_rejeu connu + fingerprint inconnu

    // ----------------------------------------------------------------
    // Authentification API
    // ----------------------------------------------------------------
    const API_KEY_MANQUANTE      = 'API_KEY_MANQUANTE';
    const API_KEY_INVALIDE       = 'API_KEY_INVALIDE';
    const API_KEY_INSUFFISANTE   = 'API_KEY_INSUFFISANTE';  // droits insuffisants (non ADMIN)

    // ----------------------------------------------------------------
    // Général
    // ----------------------------------------------------------------
    const ERREUR_INTERNE         = 'ERREUR_INTERNE';
    const VALIDATION_ECHOUEE     = 'VALIDATION_ECHOUEE';
    const RESSOURCE_INTROUVABLE  = 'RESSOURCE_INTROUVABLE';
}
