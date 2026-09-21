<?php

namespace App\Services;

use App\Models\AutoAction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Setiap panggilan Meta Graph API melalui kelas ini.
 *
 * Perhatikan apa yang TIADA di sini: tiada method updateCampaign,
 * updateAdSet atau updateBudget. Ketiadaan itu disengajakan dan merupakan
 * peraturan mutlak #1 — mengedit campaign sedia ada me-reset pembelajaran
 * algoritma Meta dan membuang duit yang sudah dibelanjakan untuk pembelajaran
 * itu. Nak ubah sesuatu? Buat campaign baru.
 */
class MetaAdsService
{
    // ─────────────────────────────────────────────────────────────────────
    // FASA 1 — creative daripada posting Page sedia ada
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cipta iklan yang creative-nya ialah posting Page sedia ada.
     *
     * Laluan utama menggunakan object_story_id, dan itu yang kita mahukan:
     * semua like, komen dan share terkumpul pada posting asal peniaga, bukan
     * berpecah merentas empat iklan asing. Peniaga nampak posting dia sendiri
     * naik.
     *
     * Tetapi sesetengah gabungan objektif dan format menolak call_to_action
     * di atas object_story_id, dan Meta tidak mendokumenkan dengan jelas yang
     * mana. Jadi kita cuba, dan bila ditolak kita salin gambar serta mesej
     * posting menjadi creative baharu — lalu merekodkan bahawa kita berbuat
     * demikian, kerana kesannya nyata kepada peniaga: bukti sosial TIDAK akan
     * terkumpul pada posting asal.
     *
     * @param  array{message?: string, full_picture?: string}|null  $postData
     *                                                                         Data posting untuk laluan fallback. Tanpanya, fallback gagal
     *                                                                         dengan mesej yang jelas dan bukan senyap.
     * @return array{ad_id: string, used_fallback: bool, object_story_id: ?string}
     */
    public function createAdFromPost(
        string $adsetId,
        string $name,
        string $pageId,
        string $postId,
        ?array $postData = null,
        ?int $adSetModelId = null,
    ): array {
        $objectStoryId = $this->composeObjectStoryId($pageId, $postId);

        try {
            $response = $this->post($this->account().'/ads', [
                'name' => $name,
                'adset_id' => $adsetId,
                'status' => 'PAUSED',
                'creative' => [
                    'object_story_id' => $objectStoryId,
                    'call_to_action' => [
                        'type' => config('dynoads.ad.call_to_action_type'),
                        'value' => ['link' => config('dynoads.ad.link')],
                    ],
                ],
            ]);

            return [
                'ad_id' => (string) $response->json('id'),
                'used_fallback' => false,
                'object_story_id' => $objectStoryId,
            ];
        } catch (MetaApiException $e) {
            if (! $this->isCtaRejection($e)) {
                throw $e;
            }

            return $this->createAdByCopyingPost(
                adsetId: $adsetId,
                name: $name,
                postId: $postId,
                postData: $postData,
                adSetModelId: $adSetModelId,
                reason: $e->getMessage(),
            );
        }
    }

    /**
     * Laluan kedua: salin gambar dan mesej posting menjadi creative baharu.
     *
     * Iklan ini berkelakuan seperti mana-mana dark post — ia berkesan, tetapi
     * engagement-nya tinggal padanya sendiri dan bukan pada posting asal.
     */
    private function createAdByCopyingPost(
        string $adsetId,
        string $name,
        string $postId,
        ?array $postData,
        ?int $adSetModelId,
        string $reason,
    ): array {
        if (blank($postData['full_picture'] ?? null)) {
            throw new RuntimeException(
                "Meta menolak call_to_action di atas posting {$postId}, dan tiada gambar posting ".
                'tersedia untuk laluan kedua. Muat semula senarai posting dan cuba lagi.'
            );
        }

        $imageHash = $this->uploadImageFromUrl($postData['full_picture']);

        $adId = $this->createAd(
            adsetId: $adsetId,
            name: $name,
            imageHash: $imageHash,
            message: (string) ($postData['message'] ?? ''),
            headline: null,
        );

        // Peraturan mutlak #4: rekod apa yang app buat bagi pihak peniaga.
        // Ini bukan log debug — ia dipapar dalam timeline dashboard supaya
        // peniaga tahu kenapa like tidak masuk ke posting asalnya.
        AutoAction::create([
            'ad_set_id' => $adSetModelId,
            'type' => AutoAction::TYPE_POST_CREATIVE_FALLBACK,
            'payload_json' => [
                'post_id' => $postId,
                'ad_id' => $adId,
                'reason' => $reason,
                'kesan' => 'Like, komen dan share iklan ini TIDAK akan terkumpul pada posting asal.',
            ],
            'executed_at' => now(),
        ]);

        return [
            'ad_id' => $adId,
            'used_fallback' => true,
            'object_story_id' => null,
        ];
    }

    /**
     * ID posting daripada Graph API sudah pun berbentuk "{page}_{post}".
     * Normalkan kedua-dua bentuk supaya kita tidak pernah menghantar
     * "{page}_{page}_{post}" yang Meta tolak dengan mesej yang mengelirukan.
     */
    public function composeObjectStoryId(string $pageId, string $postId): string
    {
        $bare = str_contains($postId, '_')
            ? substr($postId, strrpos($postId, '_') + 1)
            : $postId;

        return $pageId.'_'.$bare;
    }

    private function isCtaRejection(MetaApiException $e): bool
    {
        $config = config('dynoads.cta_rejection');

        if (in_array($e->subcode, $config['subcodes'], true)) {
            return true;
        }

        if (! in_array($e->metaCode, $config['codes'], true)) {
            return false;
        }

        $message = strtolower($e->getMessage());

        foreach ($config['message_patterns'] as $pattern) {
            if (str_contains($message, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ANDAIAN FASA 0 — method di bawah mewakili yang sudah wujud dalam app
    // live. Gantikan dengan versi sebenar anda semasa merge.
    // ─────────────────────────────────────────────────────────────────────

    public function uploadImage(string $path): string
    {
        $response = $this->post($this->account().'/adimages', [
            'bytes' => base64_encode(file_get_contents($path)),
        ]);

        return $this->firstImageHash($response);
    }

    public function uploadImageFromUrl(string $url): string
    {
        $image = Http::timeout(30)->get($url);

        if ($image->failed()) {
            throw new RuntimeException("Gagal muat turun gambar posting: {$url}");
        }

        $response = $this->post($this->account().'/adimages', [
            'bytes' => base64_encode($image->body()),
        ]);

        return $this->firstImageHash($response);
    }

    public function createCampaign(string $name, int $dailyBudgetSen): string
    {
        $response = $this->post($this->account().'/campaigns', [
            'name' => $name,
            'objective' => config('dynoads.campaign.objective'),
            'bid_strategy' => config('dynoads.campaign.bid_strategy'),
            'special_ad_categories' => config('dynoads.campaign.special_ad_categories'),
            'daily_budget' => $dailyBudgetSen,
            'status' => config('dynoads.campaign.status'),
        ]);

        return (string) $response->json('id');
    }

    public function createAdSet(string $campaignId, string $name, array $geo = []): string
    {
        $targeting = [
            'age_min' => config('dynoads.targeting.age_min'),
            'age_max' => config('dynoads.targeting.age_max'),
            'locales' => config('dynoads.targeting.locales'),
            'targeting_automation' => ['advantage_audience' => config('dynoads.targeting.advantage_audience')],
            'brand_safety_content_filter_levels' => config('dynoads.targeting.brand_safety_content_filter_levels'),
            'geo_locations' => [
                'countries' => $geo['countries'] ?? config('dynoads.targeting.countries'),
                'location_types' => config('dynoads.targeting.location_types'),
            ],
            'excluded_geo_locations' => [
                'regions' => $geo['excluded_regions'] ?? config('dynoads.targeting.excluded_regions'),
            ],
        ];

        $response = $this->post($this->account().'/adsets', [
            'name' => $name,
            'campaign_id' => $campaignId,
            'optimization_goal' => config('dynoads.adset.optimization_goal'),
            'destination_type' => config('dynoads.adset.destination_type'),
            'billing_event' => config('dynoads.adset.billing_event'),
            'promoted_object' => [
                'page_id' => config('dynoads.page_id'),
                'whatsapp_phone_number' => config('dynoads.wa_phone'),
            ],
            'targeting' => $targeting,
            'status' => config('dynoads.adset.status'),
        ]);

        return (string) $response->json('id');
    }

    public function createAd(string $adsetId, string $name, string $imageHash, string $message, ?string $headline = null): string
    {
        $linkData = [
            'link' => config('dynoads.ad.link'),
            'message' => $message,
            'image_hash' => $imageHash,
            'call_to_action' => [
                'type' => config('dynoads.ad.call_to_action_type'),
                'value' => ['link' => config('dynoads.ad.link')],
            ],
        ];

        if (filled($headline)) {
            $linkData['name'] = $headline;
        }

        $response = $this->post($this->account().'/ads', [
            'name' => $name,
            'adset_id' => $adsetId,
            'status' => 'PAUSED',
            'creative' => [
                'object_story_spec' => [
                    'page_id' => config('dynoads.page_id'),
                    'link_data' => $linkData,
                ],
            ],
        ]);

        return (string) $response->json('id');
    }

    public function enable(string ...$ids): void
    {
        foreach ($ids as $id) {
            $this->post($id, ['status' => 'ACTIVE']);
        }
    }

    public function pause(string ...$ids): void
    {
        foreach ($ids as $id) {
            $this->post($id, ['status' => 'PAUSED']);
        }
    }

    public function getInsights(array $campaignIds, string $since, string $until): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $response = $this->get($this->account().'/insights', [
            'level' => 'campaign',
            'fields' => 'campaign_id,spend,impressions,clicks,actions',
            'time_range' => json_encode(['since' => $since, 'until' => $until]),
            'filtering' => json_encode([[
                'field' => 'campaign.id',
                'operator' => 'IN',
                'value' => $campaignIds,
            ]]),
        ]);

        return $response->json('data', []);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function firstImageHash(Response $response): string
    {
        $images = $response->json('images', []);
        $first = reset($images);

        if (! is_array($first) || blank($first['hash'] ?? null)) {
            throw new RuntimeException('Meta tidak memulangkan image_hash.');
        }

        return $first['hash'];
    }

    private function account(): string
    {
        $id = config('dynoads.ad_account_id');

        if (blank($id)) {
            throw new RuntimeException('META_AD_ACCOUNT_ID tidak ditetapkan.');
        }

        return $id;
    }

    private function url(string $path): string
    {
        return sprintf('%s/%s/%s', config('dynoads.graph_url'), config('dynoads.api_version'), $path);
    }

    private function post(string $path, array $payload): Response
    {
        $payload = $this->encodeNested($payload);
        $payload['access_token'] = $this->token();

        return $this->handle(Http::asForm()->timeout(60)->post($this->url($path), $payload));
    }

    private function get(string $path, array $query): Response
    {
        $query['access_token'] = $this->token();

        return $this->handle(Http::timeout(30)->get($this->url($path), $query));
    }

    /** Graph API menerima struktur bersarang sebagai JSON dalam medan borang. */
    private function encodeNested(array $payload): array
    {
        return array_map(
            fn ($value) => is_array($value) ? json_encode($value) : $value,
            $payload,
        );
    }

    private function token(): string
    {
        $token = config('dynoads.access_token');

        if (blank($token)) {
            throw new RuntimeException('META_ACCESS_TOKEN tidak ditetapkan.');
        }

        return $token;
    }

    private function handle(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw new MetaApiException(
            message: (string) ($response->json('error.message') ?? "Meta API ralat {$response->status()}"),
            metaCode: (int) ($response->json('error.code') ?? 0),
            subcode: (int) ($response->json('error.error_subcode') ?? 0),
            status: $response->status(),
        );
    }
}
