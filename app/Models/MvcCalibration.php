<?php

namespace App\Models;

use App\Enums\Muscle;
use App\Enums\MuscleSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MvcCalibration extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'athlete_profile_id',
        'muscle',
        'side',
        'mvc_value',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'muscle' => Muscle::class,
            'side' => MuscleSide::class,
            'mvc_value' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function athleteProfile(): BelongsTo
    {
        return $this->belongsTo(AthleteProfile::class);
    }
}
