<?php

use App\Models\AdSet;
use App\Models\AutoAction;
use App\Services\MetaAdsService;
use App\Services\MetaApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('dynoads.access_token', 'test-token');
    config()->set('dynoads.ad_account_id', 'act_701202512896650');
    config()->set('dynoads.page_id', '377330642350146');
    config()->set('dynoads.wa_phone', '60187922844');
    config()->set('dynoads.api_version', 'v21.0');
    config()->set('dynoads.graph_url', 'https://graph.facebook.com');
});

/** Bentuk ralat Meta bila call_to_action ditolak di atas object_story_id. */
function ctaRejection(): array
{
    return ['error' => [
        'message' => 'Invalid parameter: call_to_action is not supported for this creative',
        'code' => 100,
        'error_subcode' => 1487831,
    ]];
}

it('menghantar object_story_id dengan format {page}_{post}', function () {
    Http::fake([
        'graph.facebook.com/*/act_*/ads' => Http::response(['id' => '23849999']),
    ]);

    $result = app(MetaAdsService::class)->createAdFromPost(
        adsetId: '1200001',
        name: 'DYNOADS-1-1-21SEP26',
        pageId: '377330642350146',
        postId: '377330642350146_101',
    );

    expect($result['ad_id'])->toBe('23849999')
        ->and($result['used_fallback'])->toBeFalse()
        ->and($result['object_story_id'])->toBe('377330642350146_101');

    Http::assertSent(function ($request) {
        $creative = json_decode($request->data()['creative'] ?? '{}', true);

        return ($creative['object_story_id'] ?? null) === '377330642350146_101'
            && ($creative['call_to_action']['type'] ?? null) === 'WHATSAPP_MESSAGE';
    });
});

it('tidak menggandakan page id bila post id datang tanpa awalan', function () {
    $meta = app(MetaAdsService::class);

    expect($meta->composeObjectStoryId('377330642350146', '101'))
        ->toBe('377330642350146_101')
        ->and($meta->composeObjectStoryId('377330642350146', '377330642350146_101'))
        ->toBe('377330642350146_101');
});

it('jatuh ke laluan kedua bila Meta tolak call to action, dan merekodnya', function () {
    $adSet = AdSet::create(['name' => 'Ujian', 'daily_budget_sen' => 3700]);

    $adsCallCount = 0;

    Http::fake([
        'scontent.example/*' => Http::response('binari-gambar-palsu'),
        'graph.facebook.com/*/act_*/adimages' => Http::response([
            'images' => ['bytes' => ['hash' => 'abc123hash']],
        ]),
        'graph.facebook.com/*/act_*/ads' => function () use (&$adsCallCount) {
            $adsCallCount++;

            // Percubaan pertama ialah laluan object_story_id; Meta tolak.
            return $adsCallCount === 1
                ? Http::response(ctaRejection(), 400)
                : Http::response(['id' => '23840000777']);
        },
    ]);

    $result = app(MetaAdsService::class)->createAdFromPost(
        adsetId: '1200001',
        name: 'DYNOADS-1-1-21SEP26',
        pageId: '377330642350146',
        postId: '377330642350146_101',
        postData: [
            'message' => 'Sistem POS sekali bayar.',
            'full_picture' => 'https://scontent.example/1.jpg',
        ],
        adSetModelId: $adSet->id,
    );

    expect($result['used_fallback'])->toBeTrue()
        ->and($result['ad_id'])->toBe('23840000777')
        ->and($result['object_story_id'])->toBeNull();

    $action = AutoAction::where('type', AutoAction::TYPE_POST_CREATIVE_FALLBACK)->first();

    expect($action)->not->toBeNull()
        ->and($action->ad_set_id)->toBe($adSet->id)
        ->and($action->payload_json['post_id'])->toBe('377330642350146_101')
        ->and($action->payload_json['kesan'])->toContain('TIDAK akan terkumpul pada posting asal');
});

it('tidak jatuh ke laluan kedua untuk ralat yang bukan tentang call to action', function () {
    Http::fake([
        'graph.facebook.com/*/act_*/ads' => Http::response([
            'error' => ['message' => 'Error validating access token: Session has expired', 'code' => 190],
        ], 401),
    ]);

    expect(fn () => app(MetaAdsService::class)->createAdFromPost(
        adsetId: '1200001',
        name: 'DYNOADS-1-1-21SEP26',
        pageId: '377330642350146',
        postId: '377330642350146_101',
        postData: ['message' => 'x', 'full_picture' => 'https://scontent.example/1.jpg'],
    ))->toThrow(MetaApiException::class);

    expect(AutoAction::count())->toBe(0);
});

it('gagal dengan mesej jelas bila fallback diperlukan tetapi gambar tiada', function () {
    Http::fake([
        'graph.facebook.com/*/act_*/ads' => Http::response(ctaRejection(), 400),
    ]);

    expect(fn () => app(MetaAdsService::class)->createAdFromPost(
        adsetId: '1200001',
        name: 'DYNOADS-1-1-21SEP26',
        pageId: '377330642350146',
        postId: '377330642350146_101',
        postData: null,
    ))->toThrow(RuntimeException::class, 'tiada gambar posting');
});
