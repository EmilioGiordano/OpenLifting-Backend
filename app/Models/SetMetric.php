<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SetMetric extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'set_id',
        'bsa_vl_pct',
        'bsa_vm_pct',
        'bsa_gmax_pct',
        'bsa_es_pct',
        'hq_ratio',
        'es_gmax_ratio',
        'intra_set_fatigue_ratio',
        'thresholds_version',
    ];

    protected $attributes = [
        'thresholds_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'bsa_vl_pct' => 'float',
            'bsa_vm_pct' => 'float',
            'bsa_gmax_pct' => 'float',
            'bsa_es_pct' => 'float',
            'hq_ratio' => 'float',
            'es_gmax_ratio' => 'float',
            'intra_set_fatigue_ratio' => 'float',
            'thresholds_version' => 'integer',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(TrainingSet::class, 'set_id');
    }
}
