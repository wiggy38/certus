<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table organisations — référentiel des clients d'Experto.
 *
 * Clé primaire : org_id (VARCHAR(10), ex: "ORG-00471") — auto-générée par le modèle.
 * org_index_b36 : représentation Base36 du même numéro séquentiel (ex: "D3" pour 471).
 * Pas de timestamps Laravel standard : cree_le / cree_par sont gérés explicitement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            // Clé primaire alphanumérique — générée par Organisation::boot()
            $table->string('org_id', 10)->primary();

            // Index séquentiel Base36 — généré conjointement à org_id
            $table->string('org_index_b36', 5)->unique();

            $table->string('nom', 150);
            $table->string('email_contact', 150);
            $table->string('telephone', 20)->nullable();
            $table->text('adresse')->nullable();

            // Code pays ISO 3166-1 alpha-2 — BF = Burkina Faso par défaut
            $table->char('pays', 2)->default('BF');

            // Horodatage et traçabilité explicites (pas de timestamps() Laravel)
            $table->dateTime('cree_le');
            $table->string('cree_par', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
