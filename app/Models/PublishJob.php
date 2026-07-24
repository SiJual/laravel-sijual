<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishJob extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'publish_jobs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'content_id',
        'platform',
        'status',
        'platform_response',
        'scheduled_at',
        'published_at',
    ];

    protected $casts = [
        'platform_response' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function contentAsset(): BelongsTo
    {
        return $this->belongsTo(ContentAsset::class, 'content_id');
    }
}
