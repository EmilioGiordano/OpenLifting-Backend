<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('training_sets')->restrictOnDelete();
            $table->text('text');
            $table->string('severity', 10);
            $table->text('evidence')->nullable();
            $table->softDeletes();

            $table->index('set_id');
        });

        DB::statement("ALTER TABLE recommendations ADD CONSTRAINT chk_recommendations_severity CHECK (severity IN ('NORMAL', 'MONITOR', 'RISK'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
