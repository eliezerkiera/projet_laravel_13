<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    use HasFactory;
    use SoftDeletes;



    public const DEFAULT_LANGUAGE = "fr";



    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag',
        'locale',
        'carbon_locale',
        'direction',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];


    public static function getDefault(): ?self
    {
        return static::where('code', self::DEFAULT_LANGUAGE)->first();
    }


    public function countries()
    {
        return $this->hasMany(Country::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
