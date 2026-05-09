<?php

namespace App\Models;

use App\Enums\Sex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AthleteProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'bodyweight_kg',
        'age_years',
        'sex',
        'calibrated_at',
    ];

    protected function casts(): array
    {
        return [
            'bodyweight_kg' => 'decimal:2',
            'age_years' => 'integer',
            'sex' => Sex::class,
            'calibrated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mvcCalibrations(): HasMany
    {
        return $this->hasMany(MvcCalibration::class);
    }
}
