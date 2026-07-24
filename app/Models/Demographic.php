<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Demographic extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'demographics';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'umkm_id',
        'analysis_id',
        'area_name',
        'population_data',
        'income_data',
        'age_distribution',
        'data_source',
        'fetched_at',
    ];

    protected $casts = [
        'population_data' => 'array',
        'income_data' => 'array',
        'age_distribution' => 'array',
        'fetched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function umkmProfile(): BelongsTo
    {
        return $this->belongsTo(UmkmProfile::class, 'umkm_id');
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(MarketAnalysis::class, 'analysis_id');
    }
}
