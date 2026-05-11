<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de un solo uso que un instructor genera para una sesión guest, y que
 * un atleta canjea vía POST /api/claim para tomar posesión de los datos.
 * "Código activo" = used_at IS NULL AND expires_at > now(). La regla "solo
 * un código activo por sesión" se aplica en la capa de aplicación (al generar
 * uno nuevo, los anteriores activos quedan con expires_at = now()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('training_sessions')->restrictOnDelete();
            $table->string('code', 8)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index('session_id');
        });

        // used_at y used_by_user_id van de la mano: ambos NULL o ambos NOT NULL
        DB::statement('
            ALTER TABLE claim_codes ADD CONSTRAINT chk_claim_codes_used_pair CHECK (
                (used_at IS NULL AND used_by_user_id IS NULL)
                OR
                (used_at IS NOT NULL AND used_by_user_id IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_codes');
    }
};
