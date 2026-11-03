<?php

use App\Livewire\AdSets\ExistingPosts;
use App\Livewire\Clients\Index;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('dynoads.access_token', 'test-token');
    config()->set('dynoads.page_id', '377330642350146');
    config()->set('dynoads.api_version', 'v21.0');
    config()->set('dynoads.graph_url', 'https://graph.facebook.com');
    Cache::flush();
});

it('menambah pelanggan dengan input gaya manusia', function () {
    Livewire::test(Index::class)
        ->set('name', 'Salon Kak Mah')
        ->set('pageId', '555000111')
        ->set('adAccountId', '999000222')
        ->set('waPhone', '012-345 6789')
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::sole();

    expect($client->ad_account_id)->toBe('act_999000222')
        ->and($client->wa_phone)->toBe('60123456789')
        ->and($client->consented_at)->toBeNull();
});

it('menolak nombor WhatsApp dan Page ID yang tak sah', function () {
    Livewire::test(Index::class)
        ->set('name', 'Kedai')
        ->set('pageId', 'facebook.com/kedai')
        ->set('adAccountId', '999000222')
        ->set('waPhone', '123')
        ->call('save')
        ->assertHasErrors(['pageId', 'waPhone']);

    expect(Client::count())->toBe(0);
});

it('persetujuan wajib ada bukti bertulis', function () {
    $client = Client::create(['name' => 'K', 'page_id' => '1111111', 'ad_account_id' => '2222222', 'wa_phone' => '60123456789']);

    Livewire::test(Index::class)
        ->call('recordConsent', $client->id)
        ->assertHasErrors("consentNotes.{$client->id}");

    expect($client->fresh()->consented_at)->toBeNull();

    Livewire::test(Index::class)
        ->set("consentNotes.{$client->id}", 'Balas Setuju di WA 3 Nov')
        ->call('recordConsent', $client->id);

    expect($client->fresh()->consented_at)->not->toBeNull();
});

it('skrin posting sedia ada membaca Page pelanggan yang dipilih', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);
    $client = Client::create(['name' => 'K', 'page_id' => '555000111', 'ad_account_id' => '2222222', 'wa_phone' => '60123456789']);

    Livewire::test(ExistingPosts::class, ['clientId' => $client->id])
        ->assertSee('Belum ada persetujuan');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/555000111/posts'));
});

it('butang lancar ditolak untuk pelanggan tanpa persetujuan', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);
    $client = Client::create(['name' => 'K', 'page_id' => '555000111', 'ad_account_id' => '2222222', 'wa_phone' => '60123456789']);

    Livewire::test(ExistingPosts::class, ['clientId' => $client->id])
        ->set('selected', ['555000111_1', '555000111_2'])
        ->call('launch')
        ->assertSet('error', fn ($e) => str_contains($e, 'persetujuan'));

    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});
