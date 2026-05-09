<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rep extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'set_id',
        'rep_number',
        'duration_ms',
    ];

    protected $attributes = [
        'duration_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rep_number' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(TrainingSet::class, 'set_id');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(MuscleActivation::class);
    }
}
