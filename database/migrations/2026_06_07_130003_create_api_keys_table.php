<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // SHA-256 de la clé en clair — jamais stockée en clair
            $table->char('cle_hash', 64)->unique();

            $table->enum('niveau', ['ADMIN', 'CLIENT'])->index();
            $table->string('nom', 100);
            $table->boolean('active')->default(true)->index();
            $table->dateTime('cree_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
