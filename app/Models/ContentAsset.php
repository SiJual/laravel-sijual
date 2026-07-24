<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentAsset extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'content_assets';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'umkm_id',
        'title',
        'content_type',
        'prompt',
        'generated_text',
        'generated_image_url',
        'caption',
        'hashtags',
        'brand_metadata',
        'tone',
        'style',
        'version',
        'status',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'brand_metadata' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function umkmProfile(): BelongsTo
    {
        return $this->belongsTo(UmkmProfile::class, 'umkm_id');
    }

    public function publishJobs(): HasMany
    {
        return $this->hasMany(PublishJob::class, 'content_id');
    }
}
