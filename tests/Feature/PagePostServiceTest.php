<?php

use App\Services\PagePostService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('dynoads.access_token', 'test-token');
    config()->set('dynoads.page_id', '377330642350146');
    config()->set('dynoads.api_version', 'v21.0');
    config()->set('dynoads.graph_url', 'https://graph.facebook.com');
    Cache::flush();
});

function graphPostsResponse(array $data, ?string $after = null): array
{
    return [
        'data' => $data,
        'paging' => $after ? ['cursors' => ['after' => $after]] : [],
    ];
}

it('memparse ringkasan reaksi, komen dan share dengan betul', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(graphPostsResponse([
            [
                'id' => '377330642350146_101',
                'message' => 'Sistem POS sekali bayar.   Tiada langganan bulanan.',
                'full_picture' => 'https://scontent.example/1.jpg',
                'permalink_url' => 'https://facebook.com/101',
                'created_time' => '2026-09-01T08:00:00+0000',
                'shares' => ['count' => 7],
                'reactions' => ['data' => [], 'summary' => ['total_count' => 132]],
                'comments' => ['data' => [], 'summary' => ['total_count' => 18]],
            ],
        ])),
    ]);

    $result = app(PagePostService::class)->listPosts();
    $post = $result['posts'][0];

    expect($post['reactions'])->toBe(132)
        ->and($post['comments'])->toBe(18)
        ->and($post['shares'])->toBe(7)
        ->and($post['id'])->toBe('377330642350146_101');
});

it('menganggap kiraan sebagai sifar bila Meta tinggalkan medan ringkasan', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(graphPostsResponse([
            [
                'id' => '377330642350146_102',
                'message' => 'Posting tanpa engagement',
                'full_picture' => 'https://scontent.example/2.jpg',
                'created_time' => '2026-09-02T08:00:00+0000',
            ],
        ])),
    ]);

    $post = app(PagePostService::class)->listPosts()['posts'][0];

    expect($post['reactions'])->toBe(0)
        ->and($post['comments'])->toBe(0)
        ->and($post['shares'])->toBe(0);
});

it('menapis keluar posting yang tiada gambar', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(graphPostsResponse([
            ['id' => '1_1', 'message' => 'Status teks sahaja', 'created_time' => '2026-09-01T08:00:00+0000'],
            [
                'id' => '1_2',
                'message' => 'Ada gambar',
                'full_picture' => 'https://scontent.example/2.jpg',
                'created_time' => '2026-09-02T08:00:00+0000',
            ],
        ])),
    ]);

    $posts = app(PagePostService::class)->listPosts()['posts'];

    expect($posts)->toHaveCount(1)
        ->and($posts[0]['id'])->toBe('1_2');
});

it('meminta medan yang betul daripada Graph API', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(graphPostsResponse([]))]);

    app(PagePostService::class)->listPosts();

    Http::assertSent(function ($request) {
        $fields = $request->data()['fields'] ?? '';

        return str_contains($request->url(), '/v21.0/377330642350146/posts')
            && str_contains($fields, 'reactions.summary(true)')
            && str_contains($fields, 'comments.summary(true)')
            && str_contains($fields, 'full_picture')
            && str_contains($fields, 'permalink_url');
    });
});

it('memulangkan cursor seterusnya untuk paging', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(graphPostsResponse([], 'CURSOR_XYZ')),
    ]);

    expect(app(PagePostService::class)->listPosts()['next_cursor'])->toBe('CURSOR_XYZ');
});

it('menyimpan hasil dalam cache supaya Graph API dipanggil sekali sahaja', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(graphPostsResponse([
            [
                'id' => '1_1',
                'message' => 'Sekali sahaja',
                'full_picture' => 'https://scontent.example/1.jpg',
                'created_time' => '2026-09-01T08:00:00+0000',
            ],
        ])),
    ]);

    $service = app(PagePostService::class);
    $service->listPosts();
    $service->listPosts();

    Http::assertSentCount(1);
});
