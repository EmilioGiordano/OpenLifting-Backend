<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('training_sessions')->restrictOnDelete();
            $table->smallInteger('set_number');
            $table->decimal('load_kg', 6, 2);
            $table->smallInteger('target_reps');
            $table->string('variant', 20);
            $table->string('depth', 20);
            $table->decimal('rpe', 3, 1);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->unique(['session_id', 'set_number'], 'uq_training_sets_set_number');
        });

        DB::statement("ALTER TABLE training_sets ADD CONSTRAINT chk_training_sets_variant CHECK (variant IN ('LOW_BAR', 'HIGH_BAR'))");
        DB::statement("ALTER TABLE training_sets ADD CONSTRAINT chk_training_sets_depth CHECK (depth IN ('ABOVE_PARALLEL', 'PARALLEL', 'BELOW_PARALLEL'))");
        DB::statement("ALTER TABLE training_sets ADD CONSTRAINT chk_training_sets_rpe CHECK (rpe BETWEEN 1.0 AND 10.0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sets');
    }
};
