<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muscle_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rep_id')->constrained('reps')->restrictOnDelete();
            $table->string('muscle', 30);
            $table->string('side', 5);
            $table->float('percent_mvc');
            $table->float('peak_percent_mvc');
            $table->softDeletes();

            $table->unique(['rep_id', 'muscle', 'side'], 'uq_muscle_activations_slot');
        });

        DB::statement("ALTER TABLE muscle_activations ADD CONSTRAINT chk_muscle_activations_muscle CHECK (muscle IN ('VASTUS_LATERALIS', 'VASTUS_MEDIALIS', 'GLUTEUS_MAXIMUS', 'ERECTOR_SPINAE', 'BICEPS_FEMORIS'))");
        DB::statement("ALTER TABLE muscle_activations ADD CONSTRAINT chk_muscle_activations_side CHECK (side IN ('LEFT', 'RIGHT'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('muscle_activations');
    }
};
