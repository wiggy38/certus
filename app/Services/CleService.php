<?php

namespace App\Services;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;

/**
 * Génération, encodage et vérification des clés de licence Experto.
 *
 * Format d'une clé :
 *   {version}.{payload_base64url}.{signature_hmac_sha256}
 *
 * Le payload (JSON) contient : organisation_id, produit, version, type,
 * date_expiration, max_activations.
 */
class CleService
{
    private const VERSION = '1';

    /**
     * Génère une clé de licence signée à partir d'un tableau de données.
     *
     * @param array{
     *   organisation_id: int,
     *   produit: string,
     *   version: string,
     *   type: string,
     *   date_expiration: string,
     *   max_activations: int
     * } $payload
     */
    public function generer(array $payload): string
    {
        $payloadJson   = json_encode($payload, JSON_THROW_ON_ERROR);
        $payloadB64    = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $enveloppe     = self::VERSION . '.' . $payloadB64;
        $signature     = $this->signer($enveloppe);

        return $enveloppe . '.' . $signature;
    }

    /**
     * Décode et vérifie une clé de licence.
     * Lance CertusException si la clé est invalide ou falsifiée.
     *
     * @return array Payload décodé
     */
    public function decoder(string $cle): array
    {
        $parties = explode('.', $cle);

        if (count($parties) !== 3) {
            throw new CertusException(
                ErrorCodes::CLE_FORMAT_INVALIDE,
                'Format de clé invalide : 3 parties attendues.',
                [],
                422,
            );
        }

        [$version, $payloadB64, $signatureRecue] = $parties;

        $enveloppe         = $version . '.' . $payloadB64;
        $signatureAttendue = $this->signer($enveloppe);

        if (! hash_equals($signatureAttendue, $signatureRecue)) {
            throw new CertusException(
                ErrorCodes::CLE_SIGNATURE_INVALIDE,
                'Signature de la clé invalide : falsification détectée.',
                [],
                403,
            );
        }

        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));

        if ($payloadJson === false) {
            throw new CertusException(ErrorCodes::CLE_FORMAT_INVALIDE, 'Payload non décodable.');
        }

        return json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Retourne true si la clé est syntaxiquement valide et signée correctement.
     * Ne vérifie pas l'expiration ni la base de données.
     */
    public function valider(string $cle): bool
    {
        try {
            $this->decoder($cle);
            return true;
        } catch (CertusException) {
            return false;
        }
    }

    private function signer(string $data): string
    {
        return hash_hmac('sha256', $data, config('app.key'));
    }
}
