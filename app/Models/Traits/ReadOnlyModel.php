<?php

namespace App\Models\Traits;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;

/**
 * Rend un modèle Eloquent immuable après sa première insertion (INSERT-ONLY).
 *
 * Comportement :
 *  - save()   → autorisé uniquement si $this->exists === false (nouvelle insertion)
 *  - update() → toujours interdit (lève CertusException 405)
 *  - delete() → toujours interdit (lève CertusException 405)
 *
 * Point d'écriture recommandé : définir une méthode ::enregistrer() statique
 * dans chaque modèle consommateur, qui construit et sauvegarde via ::create()
 * ou new self() + save().
 *
 * Note : Model::query()->update([...]) (query builder) contourne cette protection.
 * La table doit être protégée au niveau des permissions MariaDB si nécessaire.
 */
trait ReadOnlyModel
{
    /**
     * Bloque toute modification après la première insertion.
     * Les nouvelles instances ($this->exists === false) sont autorisées.
     *
     * @throws CertusException HTTP 405 si l'enregistrement existe déjà en base
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new CertusException(
                ErrorCodes::ERREUR_INTERNE,
                sprintf(
                    'La table "%s" est immuable (INSERT-ONLY) : save() sur un enregistrement existant est interdit.',
                    $this->getTable(),
                ),
                ['table' => $this->getTable(), 'pk' => $this->getKey()],
                405,
            );
        }

        return parent::save($options);
    }

    /**
     * Interdit la mise à jour via l'instance Eloquent.
     *
     * @throws CertusException HTTP 405
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new CertusException(
            ErrorCodes::ERREUR_INTERNE,
            sprintf('La table "%s" est immuable (INSERT-ONLY) : update() est interdit.', $this->getTable()),
            ['table' => $this->getTable()],
            405,
        );
    }

    /**
     * Interdit la suppression de l'enregistrement.
     *
     * @throws CertusException HTTP 405
     */
    public function delete(): bool|null
    {
        throw new CertusException(
            ErrorCodes::ERREUR_INTERNE,
            sprintf('La table "%s" est immuable (INSERT-ONLY) : delete() est interdit.', $this->getTable()),
            ['table' => $this->getTable()],
            405,
        );
    }
}
