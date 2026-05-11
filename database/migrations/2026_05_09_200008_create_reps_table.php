<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `reps` includes the muscle activation values inline as 20 nullable columns
 * (5 musculos × 2 lados × {avg pct, peak pct}). The schema of measurement
 * slots is closed by the project scope, always read together, and never
 * filtered by individual muscle — a wide table fits the access pattern,
 * keeps the ERD legible for thesis exposition, and avoids the row explosion
 * of a 1:N `muscle_activations` table (10 rows per rep).
 *
 * NULL = electrode failure / not measured. CHECK in (0, 300] permits dynamic
 * exercise peaks (>100% MVC is normal because the isometric MVC test under-
 * estimates true max) but rejects outliers from loose electrodes / noise.
 * See ADR-0001.
 */
return new class extends Migration
{
    private array $muscles = [
        'vastus_lateralis',
        'vastus_medialis',
        'gluteus_maximus',
        'erector_spinae',
        'biceps_femoris',
    ];

    private array $sides = ['left', 'right'];

    public function up(): void
    {
        Schema::create('reps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('training_sets')->restrictOnDelete();
            $table->smallInteger('rep_number');
            $table->integer('duration_ms')->default(0);

            foreach ($this->muscles as $muscle) {
                foreach ($this->sides as $side) {
                    $table->float("{$muscle}_{$side}_pct")->nullable();
                    $table->float("{$muscle}_{$side}_peak_pct")->nullable();
                }
            }

            $table->softDeletes();

            $table->unique(['set_id', 'rep_number'], 'uq_reps_rep_number');
        });

        // CHECK constraints (NULL passes by 3VL — no IS NULL clause needed).
        foreach ($this->muscles as $muscle) {
            foreach ($this->sides as $side) {
                $col = "{$muscle}_{$side}_pct";
                $colPeak = "{$muscle}_{$side}_peak_pct";
                DB::statement("ALTER TABLE reps ADD CONSTRAINT chk_reps_{$col} CHECK ({$col} > 0 AND {$col} <= 300)");
                DB::statement("ALTER TABLE reps ADD CONSTRAINT chk_reps_{$colPeak} CHECK ({$colPeak} > 0 AND {$colPeak} <= 300)");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reps');
    }
};
