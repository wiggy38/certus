<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table licences — licences logicielles Experto émises pour les organisations.
 *
 * licence_id : VARCHAR(15), auto-généré par Licence::boot() → "LIC-0000000001"
 * type_licence : CHAR(1) — 1=STARTER 2=STANDARD 3=PRO 4=ENTERPRISE 9=EVAL
 * anti_rejeu   : CHAR(2) — jeton aléatoire généré à la création (protection replay)
 * cle_hash_sha256 : CHAR(64) UNIQUE — SHA-256 de la clé en clair (stockée côté client)
 * crc_g5       : CHAR(5) — somme de contrôle 5 chars sur les champs métier
 * Pas de timestamps Laravel : cree_le / modifie_le gérés explicitement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table) {
            // Clé primaire alphanumérique — générée par Licence::boot()
            $table->string('licence_id', 15)->primary();

            // Organisation propriétaire
            $table->string('org_id', 10);
            $table->foreign('org_id')
                  ->references('org_id')
                  ->on('organisations')
                  ->restrictOnDelete();

            // Type de licence : 1=STARTER 2=STANDARD 3=PRO 4=ENTERPRISE 9=EVAL
            $table->char('type_licence', 1);

            // Quotas
            $table->smallInteger('nb_postes')->default(0);
            $table->smallInteger('nb_sites')->default(0);
            $table->smallInteger('nb_projets')->default(0);

            // Période de validité
            $table->date('date_emission');
            $table->date('date_expiration');

            // Versionnement du format de clé (évolution future du protocole)
            $table->unsignedTinyInteger('version_format')->default(1);

            // Sécurité
            $table->char('anti_rejeu', 2);                        // jeton anti-replay 2 chars
            $table->char('cle_hash_sha256', 64)->unique();        // empreinte SHA-256 de la clé
            $table->char('crc_g5', 5);                            // checksum 5 chars

            // Cycle de vie
            $table->string('statut', 12)->default('ACTIVE');      // ACTIVE | SUSPENDUE | EXPIREE | REVOQUEE
            $table->smallInteger('nb_activations')->default(0);
            $table->smallInteger('tentatives_suspectes')->default(0);

            $table->text('notes')->nullable();

            // Traçabilité manuelle (pas de timestamps() Laravel)
            $table->string('cree_par', 50);
            $table->dateTime('cree_le');
            $table->dateTime('modifie_le')->nullable();
            $table->string('modifie_par', 50)->nullable();

            // Index de performance (cle_hash_sha256 déjà indexé via unique())
            $table->index('statut');
            $table->index('date_expiration');
            $table->index('anti_rejeu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
