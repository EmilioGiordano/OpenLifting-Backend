<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_athlete', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('athlete_id');
            $table->timestamp('linked_at')->useCurrent();
            $table->softDeletes();

            $table->primary(['instructor_id', 'athlete_id']);
            $table->foreign('instructor_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('athlete_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('athlete_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_athlete');
    }
};
