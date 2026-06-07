<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table activation_historique — journal immuable des événements d'activation.
 *
 * RÈGLE STRICTE : INSERT ONLY — aucun UPDATE ni DELETE n'est autorisé.
 * Toute tentative de modification via le modèle Eloquent lève une CertusException.
 *
 * Événements possibles :
 *   ACTIVATION    — première mise en service de la licence sur cette machine
 *   DESACTIVATION — désactivation volontaire (libération de slot)
 *   REACTIVATION  — réactivation après une désactivation
 *   EXPIRATION    — désactivation automatique suite à l'expiration de la licence
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_historique', function (Blueprint $table) {
            $table->bigIncrements('histo_id');

            $table->unsignedBigInteger('activation_id');
            $table->foreign('activation_id')
                  ->references('activation_id')
                  ->on('activations')
                  ->restrictOnDelete();

            $table->string('licence_id', 15);
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->restrictOnDelete();

            // ACTIVATION | DESACTIVATION | REACTIVATION | EXPIRATION
            $table->string('evenement', 15);

            $table->string('acteur', 50);          // user id, 'systeme', 'api', etc.
            $table->string('ip_source', 45)->nullable();
            $table->string('motif', 100)->nullable();
            $table->dateTime('horodatage');

            // Index pour les requêtes d'audit et de monitoring
            $table->index('activation_id');
            $table->index('licence_id');
            $table->index('evenement');
            $table->index('horodatage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_historique');
    }
};
