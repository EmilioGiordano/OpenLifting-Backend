<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->decimal('bodyweight_kg', 5, 2);
            $table->smallInteger('age_years');
            $table->string('sex', 10);
            $table->timestamp('calibrated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE athlete_profiles ADD CONSTRAINT chk_athlete_profiles_sex CHECK (sex IN ('MALE', 'FEMALE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_profiles');
    }
};
