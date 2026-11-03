<?php

use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\AutoAction;
use App\Models\Client;
use App\Services\AdLauncher;
use App\Services\MetaAdsService;
use App\Services\PagePostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Akaun DynoPOS sendiri.
    config()->set('dynoads.access_token', 'test-token');
    config()->set('dynoads.ad_account_id', 'act_701202512896650');
    config()->set('dynoads.page_id', '377330642350146');
    config()->set('dynoads.wa_phone', '60187922844');
    config()->set('dynoads.api_version', 'v21.0');
    config()->set('dynoads.graph_url', 'https://graph.facebook.com');
    Cache::flush();
});

function salonKakMah(array $overrides = []): Client
{
    return Client::create($overrides + [
        'name' => 'Salon Kak Mah',
        'page_id' => '555000111',
        'ad_account_id' => '999000222',
        'wa_phone' => '012-345 6789',
        'consented_at' => now(),
        'consent_note' => 'Balas Setuju di WA',
    ]);
}

/** Respons Meta palsu untuk satu pelancaran penuh. */
function fakeMetaLaunch(): void
{
    Http::fake([
        'graph.facebook.com/*/campaigns' => Http::response(['id' => 'C1']),
        'graph.facebook.com/*/adsets' => Http::response(['id' => 'AS1']),
        'graph.facebook.com/*/ads' => Http::response(['id' => 'AD1']),
    ]);
}

it('menormalkan ID akaun iklan dan nombor WhatsApp', function () {
    $client = salonKakMah();

    expect($client->ad_account_id)->toBe('act_999000222')
        ->and($client->wa_phone)->toBe('60123456789')
        ->and(Client::normaliseAdAccountId('act_999000222'))->toBe('act_999000222');
});

it('menyasarkan akaun iklan, Page dan WhatsApp pelanggan', function () {
    fakeMetaLaunch();
    $meta = app(MetaAdsService::class)->forClient(salonKakMah());

    $meta->createCampaign('X', 3700);
    $meta->createAdSet('C1', 'X');
    $meta->createAd('AS1', 'X', 'hash', 'mesej');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/act_999000222/campaigns'));

    Http::assertSent(function (Request $r) {
        $promoted = json_decode($r->data()['promoted_object'] ?? '{}', true);

        return str_ends_with($r->url(), '/act_999000222/adsets')
            && $promoted === ['page_id' => '555000111', 'whatsapp_phone_number' => '60123456789'];
    });

    Http::assertSent(function (Request $r) {
        $creative = json_decode($r->data()['creative'] ?? '{}', true);

        return str_ends_with($r->url(), '/act_999000222/ads')
            && $creative['object_story_spec']['page_id'] === '555000111';
    });

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '701202512896650'));
});

it('tidak mengubah service asal selepas forClient', function () {
    $meta = app(MetaAdsService::class);
    $meta->forClient(salonKakMah());

    expect($meta->target()->adAccountId)->toBe('act_701202512896650')
        ->and(app(MetaAdsService::class)->target()->pageId)->toBe('377330642350146');
});

it('melancar iklan posting sedia ada ke akaun dan Page pelanggan', function () {
    fakeMetaLaunch();
    $client = salonKakMah();

    $adSet = AdSet::create(['client_id' => $client->id, 'name' => 'Ujian', 'daily_budget_sen' => 3700]);
    AdVariant::create([
        'ad_set_id' => $adSet->id,
        'source_type' => AdVariant::SOURCE_EXISTING_POST,
        'source_post_id' => '555000111_42',
        'image_path' => 'https://scontent.example/1.jpg',
    ]);

    $result = app(AdLauncher::class)->launch($adSet->fresh());

    expect($result['launched'])->toBe(1);

    Http::assertSent(function (Request $r) {
        $creative = json_decode($r->data()['creative'] ?? '{}', true);

        return str_ends_with($r->url(), '/act_999000222/ads')
            && ($creative['object_story_id'] ?? null) === '555000111_42';
    });
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '701202512896650'));

    $launch = AutoAction::where('type', AutoAction::TYPE_LAUNCH)->first();
    expect($launch->payload_json['client_id'])->toBe($client->id)
        ->and($launch->payload_json['ad_account_id'])->toBe('act_999000222');
});

it('ad set tanpa pelanggan masih guna akaun DynoPOS dari .env', function () {
    fakeMetaLaunch();

    $adSet = AdSet::create(['name' => 'Ujian', 'daily_budget_sen' => 3700]);
    AdVariant::create(['ad_set_id' => $adSet->id, 'image_hash' => 'h', 'caption' => 'c']);

    app(AdLauncher::class)->launch($adSet->fresh());

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/act_701202512896650/campaigns'));
});

it('enggan melancar untuk pelanggan tanpa persetujuan, tanpa satu pun panggilan ke Meta', function () {
    Http::fake();
    $client = salonKakMah(['consented_at' => null]);

    $adSet = AdSet::create(['client_id' => $client->id, 'name' => 'Ujian', 'daily_budget_sen' => 3700]);
    AdVariant::create(['ad_set_id' => $adSet->id, 'image_hash' => 'h', 'caption' => 'c']);

    expect(fn () => app(AdLauncher::class)->launch($adSet->fresh()))
        ->toThrow(RuntimeException::class, 'persetujuan');

    Http::assertNothingSent();
});

it('enggan melancar untuk pelanggan yang tidak aktif', function () {
    Http::fake();
    $client = salonKakMah(['is_active' => false]);

    $adSet = AdSet::create(['client_id' => $client->id, 'name' => 'Ujian', 'daily_budget_sen' => 3700]);

    expect(fn () => app(AdLauncher::class)->launch($adSet->fresh()))
        ->toThrow(RuntimeException::class, 'tidak aktif');

    Http::assertNothingSent();
});

it('semak akses membaca nama Page dan akaun iklan dengan GET sahaja', function () {
    Http::fake([
        'graph.facebook.com/*/555000111*' => Http::response(['id' => '555000111', 'name' => 'Salon Kak Mah Official']),
        'graph.facebook.com/*/act_999000222*' => Http::response(['id' => 'act_999000222', 'name' => 'Iklan Kak Mah', 'account_status' => 1]),
    ]);

    $result = app(MetaAdsService::class)->forClient(salonKakMah())->checkAccess();

    expect($result)->toBe([
        'page_name' => 'Salon Kak Mah Official',
        'ad_account_name' => 'Iklan Kak Mah',
        'account_status' => 1,
    ]);

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
});

it('semak akses memberi panduan jelas bila Partner Access belum diberi', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Unsupported get request.', 'code' => 100, 'error_subcode' => 33],
        ], 400),
    ]);

    expect(fn () => app(MetaAdsService::class)->forClient(salonKakMah())->checkAccess())
        ->toThrow(RuntimeException::class, 'Partner Access');
});

it('senarai posting dibaca dari Page pelanggan', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

    (new PagePostService(salonKakMah()->page_id))->listPosts();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v21.0/555000111/posts'));
});
