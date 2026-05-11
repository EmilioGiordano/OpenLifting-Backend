<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1 fila por sujeto (athlete_profile O guest_profile, XOR). Layout wide:
 * 10 columnas %MVC, una por slot (5 músculos × 2 lados). NULL = ese slot no
 * se calibró todavía. Valores en (0, 100] — el MVC es el peak normalizado del
 * test isométrico por definición. Ver ADR-0001.
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
        Schema::create('mvc_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->nullable()->constrained('athlete_profiles')->restrictOnDelete();
            $table->foreignId('guest_profile_id')->nullable()->constrained('guest_profiles')->restrictOnDelete();

            foreach ($this->muscles as $muscle) {
                foreach ($this->sides as $side) {
                    $table->float("{$muscle}_{$side}")->nullable();
                }
            }

            $table->timestamp('recorded_at')->useCurrent();
            $table->softDeletes();
        });

        // XOR: exactamente uno de los dos profiles tiene que estar seteado
        DB::statement('
            ALTER TABLE mvc_calibrations ADD CONSTRAINT chk_mvc_calibrations_owner CHECK (
                (athlete_profile_id IS NOT NULL)::int + (guest_profile_id IS NOT NULL)::int = 1
            )
        ');

        // 10 CHECK constraints (0, 100]; NULL pasa automáticamente (3VL).
        foreach ($this->muscles as $muscle) {
            foreach ($this->sides as $side) {
                $col = "{$muscle}_{$side}";
                DB::statement("ALTER TABLE mvc_calibrations ADD CONSTRAINT chk_mvc_{$col} CHECK ({$col} > 0 AND {$col} <= 100)");
            }
        }

        // 1:1 con el profile. Partial unique indexes (la columna del otro tipo
        // está NULL y un UNIQUE compuesto trataría a los NULL como distintos).
        DB::statement('CREATE UNIQUE INDEX uq_mvc_calibrations_athlete ON mvc_calibrations (athlete_profile_id) WHERE athlete_profile_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_mvc_calibrations_guest   ON mvc_calibrations (guest_profile_id)   WHERE guest_profile_id   IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('mvc_calibrations');
    }
};
