<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception métier Certus.
 *
 * Toute erreur fonctionnelle du système (licence invalide, fingerprint blacklisté,
 * signal piratage détecté…) doit lever cette exception plutôt qu'une exception
 * générique, afin d'être capturée et transformée en réponse JSON structurée.
 */
class CertusException extends RuntimeException
{
    /**
     * @param string     $codeErreur Code parmi App\Constants\ErrorCodes
     * @param string     $message    Message lisible (pour les logs)
     * @param array      $contexte   Données supplémentaires de contexte (jamais de secrets)
     * @param int        $httpStatus Code HTTP à retourner (défaut 422)
     * @param \Throwable|null $previous Exception d'origine
     */
    public function __construct(
        private readonly string $codeErreur,
        string $message = '',
        private readonly array $contexte = [],
        int $httpStatus = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: $codeErreur, $httpStatus, $previous);
    }

    public function getCodeErreur(): string
    {
        return $this->codeErreur;
    }

    public function getContexte(): array
    {
        return $this->contexte;
    }

    /** Code HTTP à utiliser dans la réponse JSON. */
    public function getHttpStatus(): int
    {
        return $this->getCode();
    }

    /** Fabrique : raccourci pour les erreurs 403 (sécurité). */
    public static function securite(string $codeErreur, string $message, array $contexte = []): self
    {
        return new self($codeErreur, $message, $contexte, 403);
    }

    /** Fabrique : raccourci pour les erreurs 404 (ressource introuvable). */
    public static function introuvable(string $codeErreur, string $message, array $contexte = []): self
    {
        return new self($codeErreur, $message, $contexte, 404);
    }
}
