<?php

namespace App\Models;

use App\Enums\SquatDepth;
use App\Enums\SquatVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSet extends Model
{
    use HasFactory, SoftDeletes;

    const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'set_number',
        'load_kg',
        'target_reps',
        'variant',
        'depth',
        'rpe',
    ];

    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'load_kg' => 'decimal:2',
            'target_reps' => 'integer',
            'variant' => SquatVariant::class,
            'depth' => SquatDepth::class,
            'rpe' => 'decimal:1',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    public function reps(): HasMany
    {
        return $this->hasMany(Rep::class, 'set_id');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(SetMetric::class, 'set_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'set_id');
    }
}
