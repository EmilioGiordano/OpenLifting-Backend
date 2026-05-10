<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mvc_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->constrained('athlete_profiles')->restrictOnDelete();
            $table->string('muscle', 30);
            $table->string('side', 5);
            $table->float('mvc_value');
            $table->timestamp('recorded_at')->useCurrent();
            $table->softDeletes();

            $table->unique(['athlete_profile_id', 'muscle', 'side'], 'uq_mvc_calibrations_slot');
        });

        DB::statement("ALTER TABLE mvc_calibrations ADD CONSTRAINT chk_mvc_calibrations_muscle CHECK (muscle IN ('VASTUS_LATERALIS', 'VASTUS_MEDIALIS', 'GLUTEUS_MAXIMUS', 'ERECTOR_SPINAE', 'BICEPS_FEMORIS'))");
        DB::statement("ALTER TABLE mvc_calibrations ADD CONSTRAINT chk_mvc_calibrations_side CHECK (side IN ('LEFT', 'RIGHT'))");
        // mvc_value is %MVC in (0, 100]; see docs/adr/0001-emg-data-scale.md.
        DB::statement("ALTER TABLE mvc_calibrations ADD CONSTRAINT chk_mvc_calibrations_value CHECK (mvc_value > 0 AND mvc_value <= 100)");
    }

    public function down(): void
    {
        Schema::dropIfExists('mvc_calibrations');
    }
};
