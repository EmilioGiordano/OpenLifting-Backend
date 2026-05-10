<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rep extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    /**
     * The 10 (muscle, side) slots, in canonical order. Used by mappers
     * that translate between API-shape `activations[]` and the flat
     * 20 columns in this table.
     */
    public const ACTIVATION_SLOTS = [
        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT',  'col' => 'vastus_lateralis_left'],
        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'RIGHT', 'col' => 'vastus_lateralis_right'],
        ['muscle' => 'VASTUS_MEDIALIS',  'side' => 'LEFT',  'col' => 'vastus_medialis_left'],
        ['muscle' => 'VASTUS_MEDIALIS',  'side' => 'RIGHT', 'col' => 'vastus_medialis_right'],
        ['muscle' => 'GLUTEUS_MAXIMUS',  'side' => 'LEFT',  'col' => 'gluteus_maximus_left'],
        ['muscle' => 'GLUTEUS_MAXIMUS',  'side' => 'RIGHT', 'col' => 'gluteus_maximus_right'],
        ['muscle' => 'ERECTOR_SPINAE',   'side' => 'LEFT',  'col' => 'erector_spinae_left'],
        ['muscle' => 'ERECTOR_SPINAE',   'side' => 'RIGHT', 'col' => 'erector_spinae_right'],
        ['muscle' => 'BICEPS_FEMORIS',   'side' => 'LEFT',  'col' => 'biceps_femoris_left'],
        ['muscle' => 'BICEPS_FEMORIS',   'side' => 'RIGHT', 'col' => 'biceps_femoris_right'],
    ];

    protected $fillable = [
        'set_id',
        'rep_number',
        'duration_ms',
        'vastus_lateralis_left_pct',  'vastus_lateralis_left_peak_pct',
        'vastus_lateralis_right_pct', 'vastus_lateralis_right_peak_pct',
        'vastus_medialis_left_pct',   'vastus_medialis_left_peak_pct',
        'vastus_medialis_right_pct',  'vastus_medialis_right_peak_pct',
        'gluteus_maximus_left_pct',   'gluteus_maximus_left_peak_pct',
        'gluteus_maximus_right_pct',  'gluteus_maximus_right_peak_pct',
        'erector_spinae_left_pct',    'erector_spinae_left_peak_pct',
        'erector_spinae_right_pct',   'erector_spinae_right_peak_pct',
        'biceps_femoris_left_pct',    'biceps_femoris_left_peak_pct',
        'biceps_femoris_right_pct',   'biceps_femoris_right_peak_pct',
    ];

    protected $attributes = [
        'duration_ms' => 0,
    ];

    protected function casts(): array
    {
        $base = [
            'rep_number' => 'integer',
            'duration_ms' => 'integer',
        ];

        foreach (self::ACTIVATION_SLOTS as $slot) {
            $base["{$slot['col']}_pct"] = 'float';
            $base["{$slot['col']}_peak_pct"] = 'float';
        }

        return $base;
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(TrainingSet::class, 'set_id');
    }

    /**
     * Map from API payload activations[] to flat column attributes.
     * Returns the column-keyed array ready for Eloquent fill/create.
     */
    public static function mapActivationsToColumns(array $activations): array
    {
        $cols = [];
        foreach ($activations as $a) {
            $slot = collect(self::ACTIVATION_SLOTS)
                ->firstWhere(fn ($s) => $s['muscle'] === $a['muscle'] && $s['side'] === $a['side']);
            if ($slot === null) {
                continue;
            }
            $cols["{$slot['col']}_pct"] = $a['percent_mvc'];
            $cols["{$slot['col']}_peak_pct"] = $a['peak_percent_mvc'];
        }

        return $cols;
    }

    /**
     * Project the flat columns back into the API payload shape:
     * [{muscle, side, percent_mvc, peak_percent_mvc}, ...].
     * Slots whose columns are both NULL are omitted (= "no measurement").
     */
    public function activationsArray(): array
    {
        $out = [];
        foreach (self::ACTIVATION_SLOTS as $slot) {
            $pct = $this->{"{$slot['col']}_pct"};
            $peak = $this->{"{$slot['col']}_peak_pct"};
            if ($pct === null && $peak === null) {
                continue;
            }
            $out[] = [
                'muscle' => $slot['muscle'],
                'side' => $slot['side'],
                'percent_mvc' => $pct !== null ? (float) $pct : null,
                'peak_percent_mvc' => $peak !== null ? (float) $peak : null,
            ];
        }

        return $out;
    }
}
