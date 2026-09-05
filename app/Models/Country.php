<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory;
    use SoftDeletes;


    public $timestamps = false;

    public const DEFAULT_COUNTRY='bf';



    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag_image',
        'phone_code',
        'currency_id',
        'language_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

       public static function getDefault(): ?self
    {
        return static::where('code', self::DEFAULT_COUNTRY)->first();
    }

    // Relations
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->orderBy('sort_order');
    }

    // URL complète du drapeau
    public function getFlagUrlAttribute(): ?string
    {
        return $this->flag_image
            ? asset('storage/' . $this->flag_image)
            : null;
    }


    public function users()
    {
        return $this->hasMany(User::class);
    }
}
