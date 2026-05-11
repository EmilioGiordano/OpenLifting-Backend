<?php

namespace App\Models;

use App\Enums\DeviceSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'athlete_user_id',
        'guest_profile_id',
        'instructor_user_id',
        'exercise',
        'started_at',
        'ended_at',
        'device_source',
    ];

    protected $attributes = [
        'exercise' => 'back_squat',
        'device_source' => 'SIMULATED',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'device_source' => DeviceSource::class,
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'athlete_user_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id');
    }

    public function guestProfile(): BelongsTo
    {
        return $this->belongsTo(GuestProfile::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(TrainingSet::class, 'session_id');
    }

    public function claimCodes(): HasMany
    {
        return $this->hasMany(ClaimCode::class, 'session_id');
    }
}
