<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'full_name',
        'phone',
        'avatar_url',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function umkmProfiles(): HasMany
    {
        return $this->hasMany(UmkmProfile::class, 'user_id');
    }
}
