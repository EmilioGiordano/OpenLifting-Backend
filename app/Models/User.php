<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function athleteProfile(): HasOne
    {
        return $this->hasOne(AthleteProfile::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'athlete_user_id');
    }

    public function instructedSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'instructor_user_id');
    }

    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'instructor_athlete', 'instructor_id', 'athlete_id')
            ->withPivot(['linked_at', 'deleted_at']);
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'instructor_athlete', 'athlete_id', 'instructor_id')
            ->withPivot(['linked_at', 'deleted_at']);
    }

    public function createdGuests(): HasMany
    {
        return $this->hasMany(GuestProfile::class, 'created_by_user_id');
    }

    public function claimedGuests(): HasMany
    {
        return $this->hasMany(GuestProfile::class, 'claimed_by_user_id');
    }

    public function isAthlete(): bool
    {
        return $this->role->name === UserRole::ATHLETE->value;
    }

    public function isInstructor(): bool
    {
        return $this->role->name === UserRole::INSTRUCTOR->value;
    }
}
