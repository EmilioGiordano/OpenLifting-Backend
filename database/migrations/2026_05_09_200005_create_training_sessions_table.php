<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('instructor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('exercise', 50)->default('back_squat');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('device_source', 20)->default('SIMULATED');
            $table->timestamps();
            $table->softDeletes();

            $table->index('athlete_user_id');
            $table->index('instructor_user_id');
        });

        DB::statement("ALTER TABLE training_sessions ADD CONSTRAINT chk_training_sessions_source CHECK (device_source IN ('REAL', 'SIMULATED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
