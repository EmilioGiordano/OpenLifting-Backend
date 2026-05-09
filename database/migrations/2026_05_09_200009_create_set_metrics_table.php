<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->unique()->constrained('training_sets')->restrictOnDelete();
            $table->float('bsa_vl_pct');
            $table->float('bsa_vm_pct');
            $table->float('bsa_gmax_pct');
            $table->float('bsa_es_pct');
            $table->float('hq_ratio');
            $table->float('es_gmax_ratio');
            $table->float('intra_set_fatigue_ratio');
            $table->smallInteger('thresholds_version')->default(1);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_metrics');
    }
};
