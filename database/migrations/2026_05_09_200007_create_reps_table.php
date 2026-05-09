<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('training_sets')->restrictOnDelete();
            $table->smallInteger('rep_number');
            $table->integer('duration_ms')->default(0);
            $table->softDeletes();

            $table->unique(['set_id', 'rep_number'], 'uq_reps_rep_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reps');
    }
};
