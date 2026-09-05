<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory;


    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'symbol_position',
        'decimal_places',
        'decimal_separator',
        'thousands_separator',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
        'decimal_places' => 'integer',
    ];

    // Scope pour ne récupérer que les devises actives
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Récupérer la devise par défaut
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    // Formater un montant selon la devise
    public function format(float $amount): string
    {
        $formatted = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousands_separator
        );

        return $this->symbol_position === 'before'
            ? "{$this->symbol} {$formatted}"
            : "{$formatted} {$this->symbol}";
    }

    public function countries()
    {
        return $this->hasMany(Country::class);
    }
}
