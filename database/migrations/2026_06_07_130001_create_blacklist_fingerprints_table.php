<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table blacklist_fingerprints — empreintes machines bannies.
 *
 * RÈGLE STRICTE : INSERT ONLY — aucun UPDATE ni DELETE autorisé.
 *
 * Un fingerprint blacklisté est refusé à toute tentative d'activation,
 * quel que soit la licence présentée.
 *
 * Signaux de piratage (colonne signal) :
 *   1 = CLONAGE         — même licence, fingerprint différent déjà actif
 *   2 = MULTI_INSTANCE  — quota de postes dépassé
 *   3 = REJEU           — nonce déjà utilisé (anti-replay)
 *   4 = FALSIFICATION   — signature HMAC de la clé invalide
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklist_fingerprints', function (Blueprint $table) {
            $table->bigIncrements('blacklist_id');

            // SHA-256 de l'empreinte machine (64 hex chars)
            $table->char('fingerprint', 64);

            $table->string('licence_id', 15);
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->restrictOnDelete();

            // Signal de piratage déclencheur : 1 | 2 | 3 | 4
            $table->unsignedTinyInteger('signal');

            $table->string('motif', 100)->nullable();

            // Administrateur ou 'SYSTEME' ayant déclenché le blocage
            $table->string('bloque_par', 50)->default('SYSTEME');

            $table->dateTime('horodatage');

            // Index principal pour la vérification rapide d'un fingerprint
            $table->index('fingerprint');
            $table->index('licence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist_fingerprints');
    }
};
