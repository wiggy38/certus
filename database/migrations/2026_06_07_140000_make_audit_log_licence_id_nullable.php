<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend audit_log.licence_id nullable pour permettre les entrées
 * d'audit au niveau organisation (CREATION_ORGANISATION) qui n'ont
 * pas encore de licence associée.
 *
 * La FK vers licences.licence_id est conservée avec nullOnDelete()
 * pour que la référence soit vérifiée quand elle est présente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropForeign(['licence_id']);
            $table->string('licence_id', 15)->nullable()->change();
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropForeign(['licence_id']);
            $table->string('licence_id', 15)->nullable(false)->change();
            $table->foreign('licence_id')
                  ->references('licence_id')
                  ->on('licences')
                  ->restrictOnDelete();
        });
    }
};
