<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UmkmProfile extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'umkm_profiles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'business_name',
        'business_type',
        'address',
        'city',
        'province',
        'latitude',
        'longitude',
        'phone',
        'logo_url',
        'profile_completeness',
        'target_cuan',
        'target_cuan_period',
        'financial_settings',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'profile_completeness' => 'integer',
        'target_cuan' => 'integer',
        'financial_settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class, 'umkm_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'umkm_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'umkm_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'umkm_id');
    }

    public function marketAnalyses(): HasMany
    {
        return $this->hasMany(MarketAnalysis::class, 'umkm_id');
    }

    public function contentAssets(): HasMany
    {
        return $this->hasMany(ContentAsset::class, 'umkm_id');
    }
}
