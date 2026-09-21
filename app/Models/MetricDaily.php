<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricDaily extends Model
{
    use HasFactory;

    protected $table = 'metrics_daily';

    protected $fillable = [
        'ad_variant_id', 'date', 'spend_sen', 'impressions',
        'clicks', 'leads', 'cost_per_lead_sen',
    ];

    protected $casts = ['date' => 'date'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AdVariant::class, 'ad_variant_id');
    }
}
