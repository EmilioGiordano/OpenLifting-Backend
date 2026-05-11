<?php

namespace App\Models;

use App\Enums\Sex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by_user_id',
        'claimed_by_user_id',
        'first_name',
        'last_name',
        'bodyweight_kg',
        'age_years',
        'sex',
        'calibrated_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'bodyweight_kg' => 'decimal:2',
            'age_years' => 'integer',
            'sex' => Sex::class,
            'calibrated_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function mvcCalibration(): HasOne
    {
        return $this->hasOne(MvcCalibration::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function isClaimed(): bool
    {
        return $this->claimed_by_user_id !== null;
    }
}
