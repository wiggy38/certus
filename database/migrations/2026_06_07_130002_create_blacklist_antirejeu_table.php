<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table blacklist_antirejeu — jetons anti-rejeu utilisés (consommés).
 *
 * RÈGLE STRICTE : INSERT ONLY — aucun UPDATE ni DELETE autorisé.
 *
 * Chaque licence porte un jeton anti_rejeu CHAR(2) généré à l'émission.
 * Lors d'une vérification, si ce jeton est présent dans cette table avec
 * un horodatage < 5 minutes, la requête est rejetée (Signal 3 - REJEU).
 *
 * Une tâche planifiée peut purger les entrées > 5 minutes pour limiter la
 * taille de la table, sans compromettre la sécurité (TTL déjà dépassé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklist_antirejeu', function (Blueprint $table) {
            $table->bigIncrements('bl_id');

            // Jeton anti-rejeu de 2 chars [A-Z0-9] (colonne anti_rejeu de licences)
            $table->char('anti_rejeu', 2);

            $table->string('licence_id', 15);
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->restrictOnDelete();

            $table->string('motif', 100)->nullable();

            // Initiateur du blocage : 'SYSTEME' ou identifiant admin
            $table->string('bloque_par', 50)->default('SYSTEME');

            $table->dateTime('horodatage');

            // Index pour la détection rapide de rejeu (TTL 5 min via horodatage)
            $table->index('anti_rejeu');
            $table->index('licence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist_antirejeu');
    }
};
