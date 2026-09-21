<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdVariant extends Model
{
    use HasFactory;

    /** Creative dibuat oleh kita — poster atau gambar yang peniaga upload. */
    public const SOURCE_POSTER = 'poster';

    /** Creative ialah posting Page yang sudah wujud. Kita hanya merujuknya. */
    public const SOURCE_EXISTING_POST = 'existing_post';

    protected $fillable = [
        'ad_set_id', 'source_type', 'source_post_id',
        'image_path', 'image_hash', 'caption', 'headline',
        'meta_campaign_id', 'meta_adset_id', 'meta_ad_id', 'status',
    ];

    protected $attributes = [
        'source_type' => self::SOURCE_POSTER,
    ];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }

    public function isFromExistingPost(): bool
    {
        return $this->source_type === self::SOURCE_EXISTING_POST;
    }
}
