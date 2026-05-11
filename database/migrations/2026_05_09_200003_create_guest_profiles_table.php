<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atleta sin cuenta creado por un instructor para registrar una sesión en frío.
 * Al reclamar (POST /api/claim con un claim_code), claimed_by_user_id se setea al
 * user_id del atleta que se hizo cuenta y los datos asociados se transfieren al
 * athlete_profile real. Los registros del guest se conservan para auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->decimal('bodyweight_kg', 5, 2);
            $table->smallInteger('age_years');
            $table->string('sex', 10);
            $table->timestamp('calibrated_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_by_user_id', 'idx_guest_profiles_creator');
            $table->index('claimed_by_user_id', 'idx_guest_profiles_claimer');
        });

        DB::statement("ALTER TABLE guest_profiles ADD CONSTRAINT chk_guest_profiles_sex        CHECK (sex IN ('MALE', 'FEMALE'))");
        DB::statement('ALTER TABLE guest_profiles ADD CONSTRAINT chk_guest_profiles_bodyweight CHECK (bodyweight_kg BETWEEN 30 AND 300)');
        DB::statement('ALTER TABLE guest_profiles ADD CONSTRAINT chk_guest_profiles_age        CHECK (age_years BETWEEN 14 AND 100)');
        // claimed_at va de la mano con claimed_by_user_id: ambos NULL o ambos NOT NULL
        DB::statement('
            ALTER TABLE guest_profiles ADD CONSTRAINT chk_guest_profiles_claim_pair CHECK (
                (claimed_by_user_id IS NULL AND claimed_at IS NULL)
                OR
                (claimed_by_user_id IS NOT NULL AND claimed_at IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_profiles');
    }
};
