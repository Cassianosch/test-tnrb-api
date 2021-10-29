<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    public $timestamps = true;
    protected $table = 'transactions';
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'amount',
        'date',
        'description',
        'image',
        'type',
        'status',
        'user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function image()
    {
        return $this->belongsTo(Image::class);
    }
}