<?php

namespace App\Services;

use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\AutoAction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Orkestra antara pangkalan data dan Meta.
 *
 * Satu variant = satu campaign. Itu yang membolehkan split test sebenar:
 * setiap creative dapat bajet sendiri dan algoritma Meta tidak boleh
 * mengalihkan semua belanja kepada satu creative sebelum kita sempat belajar
 * apa-apa daripada yang lain.
 *
 * Semua yang dibuat mendarat dalam keadaan PAUSED. Peraturan mutlak #3 —
 * tiada duit bergerak sehingga peniaga menekan RUN.
 */
class AdLauncher
{
    public function __construct(
        private readonly MetaAdsService $meta,
    ) {}

    /**
     * @return array{launched: int, failed: int, fallbacks: int}
     */
    public function launch(AdSet $adSet): array
    {
        // Ditolak sebelum satu pun panggilan ke Meta. Duit pelanggan, kad
        // pelanggan — tanpa persetujuan mereka, tiada apa-apa dibuat.
        if ($blocker = $adSet->client?->launchBlocker()) {
            throw new RuntimeException($blocker);
        }

        $meta = $this->meta->forClient($adSet->client);
        $summary = ['launched' => 0, 'failed' => 0, 'fallbacks' => 0];
        $date = now()->format('dMy');

        foreach ($adSet->variants()->orderBy('id')->get() as $index => $variant) {
            $name = $this->nameFor($adSet, $index + 1, $date);

            try {
                $this->launchVariant($meta, $adSet, $variant, $name, $summary);
                $summary['launched']++;
            } catch (Throwable $e) {
                // Satu variant gagal tidak sepatutnya menjatuhkan yang lain.
                // Peniaga lebih rela dapat tiga daripada empat iklan berbanding
                // satu skrin ralat dan tiada apa-apa.
                $summary['failed']++;
                $variant->update(['status' => 'failed']);

                AutoAction::create([
                    'ad_set_id' => $adSet->id,
                    'type' => AutoAction::TYPE_LAUNCH,
                    'payload_json' => [
                        'variant_id' => $variant->id,
                        'source_type' => $variant->source_type,
                        'ralat' => $e->getMessage(),
                    ],
                    'executed_at' => now(),
                ]);
            }
        }

        $adSet->update(['status' => $summary['launched'] > 0 ? 'ready' : 'failed']);

        return $summary;
    }

    private function launchVariant(MetaAdsService $meta, AdSet $adSet, AdVariant $variant, string $name, array &$summary): void
    {
        $campaignId = $meta->createCampaign($name, $adSet->daily_budget_sen);
        $adsetId = $meta->createAdSet($campaignId, $name, $adSet->geo_json ?? []);

        if ($variant->isFromExistingPost()) {
            $result = $meta->createAdFromPost(
                adsetId: $adsetId,
                name: $name,
                pageId: $meta->target()->pageId,
                postId: $variant->source_post_id,
                postData: [
                    'message' => $variant->caption,
                    'full_picture' => $variant->image_path,
                ],
                adSetModelId: $adSet->id,
            );

            $adId = $result['ad_id'];

            if ($result['used_fallback']) {
                $summary['fallbacks']++;
            }
        } else {
            $adId = $meta->createAd(
                adsetId: $adsetId,
                name: $name,
                imageHash: $variant->image_hash,
                message: (string) $variant->caption,
                headline: $variant->headline,
            );
        }

        DB::transaction(function () use ($meta, $variant, $adSet, $campaignId, $adsetId, $adId) {
            $variant->update([
                'meta_campaign_id' => $campaignId,
                'meta_adset_id' => $adsetId,
                'meta_ad_id' => $adId,
                'status' => 'paused',
            ]);

            AutoAction::create([
                'ad_set_id' => $adSet->id,
                'type' => AutoAction::TYPE_LAUNCH,
                'payload_json' => [
                    'variant_id' => $variant->id,
                    'source_type' => $variant->source_type,
                    'client_id' => $adSet->client_id,
                    'ad_account_id' => $meta->target()->adAccountId,
                    'campaign_id' => $campaignId,
                    'ad_id' => $adId,
                ],
                'executed_at' => now(),
            ]);
        });
    }

    /** DYNOADS-{setId}-{n}-{DDMMMYY} */
    private function nameFor(AdSet $adSet, int $n, string $date): string
    {
        return str_replace(
            ['{setId}', '{n}', '{date}'],
            [$adSet->id, $n, strtoupper($date)],
            config('dynoads.defaults.name_format'),
        );
    }
}
