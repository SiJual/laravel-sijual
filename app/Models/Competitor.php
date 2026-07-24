<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Competitor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'competitors';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'analysis_id',
        'name',
        'business_type',
        'address',
        'latitude',
        'longitude',
        'rating',
        'review_count',
        'sentiment',
        'scraped_data',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'rating' => 'float',
        'review_count' => 'integer',
        'scraped_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function marketAnalysis(): BelongsTo
    {
        return $this->belongsTo(MarketAnalysis::class, 'analysis_id');
    }
}
