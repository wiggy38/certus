<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table audit_log — journal immuable de toutes les actions API sur les licences.
 *
 * RÈGLE STRICTE : INSERT ONLY — aucun UPDATE ni DELETE autorisé.
 * Chaque appel qui modifie l'état d'une licence doit produire une entrée.
 *
 * Actions documentées (constantes dans AuditLog::ACTION_*) :
 *   LICENCE_VERIFIEE, LICENCE_ACTIVEE, LICENCE_DESACTIVEE,
 *   LICENCE_SUSPENDUE, LICENCE_REVOQUEE, LICENCE_EXPIREE,
 *   HEARTBEAT, FP_BLACKLISTE, FP_SUPPRIME,
 *   PIRATAGE_DETECTE, ACTIVATION_REACTIVE
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('log_id');

            $table->string('licence_id', 15);
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->restrictOnDelete();

            // Action effectuée — voir constantes AuditLog::ACTION_*
            $table->string('action', 30);

            // Initiateur : identifiant utilisateur, clé API tronquée, 'systeme'
            $table->string('acteur', 50);

            $table->string('ip_source', 45)->nullable();

            // Contexte additionnel (JSON encodé ou texte libre)
            $table->text('detail')->nullable();

            $table->dateTime('horodatage');

            // Index pour les requêtes d'audit et monitoring
            $table->index('licence_id');
            $table->index('action');
            $table->index('horodatage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
