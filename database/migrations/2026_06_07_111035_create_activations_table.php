<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table activations — instances actives d'une licence sur une machine.
 *
 * Une activation = une machine identifiée par son fingerprint SHA-256.
 * La traçabilité temporelle est déléguée à activation_historique.
 *
 * Index composites justifiés :
 *  (licence_id, statut)              → lister les actives d'une licence
 *  (licence_id, fingerprint, statut) → détecter clonage / multi-instance
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activations', function (Blueprint $table) {
            $table->bigIncrements('activation_id');

            $table->string('licence_id', 15);
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->restrictOnDelete();

            // Empreinte SHA-256 de la machine (64 hex) — identifiant stable
            $table->char('fingerprint', 64);

            // Statut courant : ACTIVE | INACTIVE | EXPIREE | REVOQUEE
            $table->string('statut', 12)->default('ACTIVE');

            // Métadonnées de la machine
            $table->string('nom_poste', 100)->nullable();
            $table->string('ip_activation', 45)->nullable();   // supporte IPv4 et IPv6
            $table->string('version_app', 10)->nullable();

            // ----------------------------------------------------------------
            // Index — performance des requêtes de détection de piratage
            // ----------------------------------------------------------------
            $table->index('fingerprint');
            $table->index(['licence_id', 'statut'],              'idx_lic_statut');
            $table->index(['licence_id', 'fingerprint', 'statut'], 'idx_lic_fp_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activations');
    }
};
