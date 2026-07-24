<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketAnalysis extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'market_analyses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'umkm_id',
        'location_query',
        'latitude',
        'longitude',
        'radius_km',
        'market_fit_score',
        'analysis_data',
        'demographic_data',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_km' => 'float',
        'market_fit_score' => 'integer',
        'analysis_data' => 'array',
        'demographic_data' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function umkmProfile(): BelongsTo
    {
        return $this->belongsTo(UmkmProfile::class, 'umkm_id');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class, 'analysis_id');
    }

    public function demographics(): HasMany
    {
        return $this->hasMany(Demographic::class, 'analysis_id');
    }
}
