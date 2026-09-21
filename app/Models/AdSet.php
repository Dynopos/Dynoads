<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'brief_id', 'name', 'pain_point', 'offer',
        'geo_json', 'daily_budget_sen', 'status', 'phase', 'round',
    ];

    protected $casts = [
        'geo_json' => 'array',
        'daily_budget_sen' => 'integer',
        'round' => 'integer',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(AdVariant::class);
    }

    public function autoActions(): HasMany
    {
        return $this->hasMany(AutoAction::class);
    }
}
