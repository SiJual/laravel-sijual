<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'products';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'umkm_id',
        'name',
        'sku',
        'category',
        'price',
        'stock_level',
        'status',
        'image_url',
        'description',
        'low_stock_threshold',
    ];

    protected $casts = [
        'price' => 'integer',
        'stock_level' => 'integer',
        'low_stock_threshold' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Allowed values for the `category` enum column, mapped to the label shown
     * in the UI. The database rejects anything outside these keys.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'food_bev' => 'Kuliner / F&B',
        'textiles' => 'Fashion & Batik',
        'handicrafts' => 'Kerajinan Tangan',
        'services' => 'Jasa',
        'other' => 'Umum',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? '—';
    }

    public function umkmProfile(): BelongsTo
    {
        return $this->belongsTo(UmkmProfile::class, 'umkm_id');
    }
}
