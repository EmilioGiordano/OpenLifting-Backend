<?php

namespace App\Models;

use App\Enums\Muscle;
use App\Enums\MuscleSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MuscleActivation extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'rep_id',
        'muscle',
        'side',
        'percent_mvc',
        'peak_percent_mvc',
    ];

    protected function casts(): array
    {
        return [
            'muscle' => Muscle::class,
            'side' => MuscleSide::class,
            'percent_mvc' => 'float',
            'peak_percent_mvc' => 'float',
        ];
    }

    public function rep(): BelongsTo
    {
        return $this->belongsTo(Rep::class);
    }
}
