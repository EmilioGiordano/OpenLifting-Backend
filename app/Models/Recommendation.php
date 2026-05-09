<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recommendation extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'set_id',
        'text',
        'severity',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'severity' => RiskLevel::class,
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(TrainingSet::class, 'set_id');
    }
}
