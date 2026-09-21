<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit bagi setiap perkara yang app lakukan kepada Meta.
 *
 * Peraturan mutlak #4: rekod dahulu, panggil API kemudian. Bila sesuatu
 * berlaku tidak seperti dijangka, baris dalam jadual ini yang menerangkan
 * kepada peniaga apa yang app buat bagi pihak dia.
 */
class AutoAction extends Model
{
    use HasFactory;

    public const TYPE_LAUNCH = 'launch';

    public const TYPE_PAUSE = 'pause';

    public const TYPE_SCALE = 'scale';

    public const TYPE_REPORT = 'report';

    /** Laluan object_story_id ditolak; kita salin posting jadi creative baharu. */
    public const TYPE_POST_CREATIVE_FALLBACK = 'post_creative_fallback';

    protected $fillable = ['ad_set_id', 'type', 'payload_json', 'executed_at'];

    protected $casts = [
        'payload_json' => 'array',
        'executed_at' => 'datetime',
    ];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }
}
