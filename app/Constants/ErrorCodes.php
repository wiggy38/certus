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
    const SIGNAL_CLONAGE         = 'SIGNAL_CLONAGE';        // même licence, fingerprint différent
    const SIGNAL_MULTI_INSTANCE  = 'SIGNAL_MULTI_INSTANCE'; // trop d'instances simultanées
    const SIGNAL_REJEU           = 'SIGNAL_REJEU';          // nonce déjà utilisé
    const SIGNAL_FALSIFICATION   = 'SIGNAL_FALSIFICATION';  // signature clé invalide

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
