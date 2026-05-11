<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El sujeto de la sesión es un athlete (athlete_user_id) O un guest
 * (guest_profile_id) — XOR enforced por CHECK. Al reclamar un guest, la fila
 * pivota: athlete_user_id pasa a estar seteado y guest_profile_id se nulea.
 * instructor_user_id queda NOT NULL cuando un instructor levantó la sesión
 * en nombre del sujeto, NULL cuando el athlete se auto-registró.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('guest_profile_id')->nullable()->constrained('guest_profiles')->restrictOnDelete();
            $table->foreignId('instructor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('exercise', 50)->default('back_squat');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('device_source', 20)->default('SIMULATED');
            $table->timestamps();
            $table->softDeletes();

            $table->index('athlete_user_id');
            $table->index('guest_profile_id');
            $table->index('instructor_user_id');
        });

        DB::statement("ALTER TABLE training_sessions ADD CONSTRAINT chk_training_sessions_source CHECK (device_source IN ('REAL', 'SIMULATED'))");
        // XOR: exactamente uno de los dos sujetos tiene que estar seteado
        DB::statement('
            ALTER TABLE training_sessions ADD CONSTRAINT chk_training_sessions_subject CHECK (
                (athlete_user_id IS NOT NULL)::int + (guest_profile_id IS NOT NULL)::int = 1
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
