<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Baca posting Page — dan hanya baca.
 *
 * PERATURAN 13: kelas ini tidak mempunyai satu pun method yang menulis,
 * memadam atau mengubah posting. Posting peniaga ialah hasil kerja mereka
 * sendiri, kadangkala bertahun-tahun terkumpul. App merujuk post_id untuk
 * dijadikan creative; ia tidak pernah menyentuh posting itu.
 *
 * Kalau anda tergoda untuk menambah method yang POST atau DELETE ke sini,
 * jangan. Ada ujian yang khusus memeriksa fail ini dan ia akan gagal.
 */
class PagePostService
{
    public function __construct(private readonly ?string $pageId = null) {}

    private function page(): string
    {
        $pageId = $this->pageId ?: config('dynoads.page_id');

        if (blank($pageId)) {
            throw new RuntimeException('META_PAGE_ID tidak ditetapkan.');
        }

        return $pageId;
    }

    /**
     * Senarai posting Page yang bergambar.
     *
     * @return array{posts: array<int, array<string, mixed>>, next_cursor: ?string}
     */
    public function listPosts(int $limit = 25, ?string $after = null): array
    {
        $limit = max(1, min($limit, 100));
        $cacheKey = sprintf('dynoads:page_posts:%s:%d:%s', $this->page(), $limit, $after ?? 'first');
        $ttl = now()->addMinutes((int) config('dynoads.page_posts.cache_minutes', 10));

        return Cache::remember($cacheKey, $ttl, function () use ($limit, $after) {
            $response = $this->get($this->page().'/posts', [
                'fields' => implode(',', [
                    'id',
                    'message',
                    'full_picture',
                    'permalink_url',
                    'created_time',
                    'shares',
                    'reactions.summary(true)',
                    'comments.summary(true)',
                ]),
                // Minta lebih daripada yang diperlukan kerana posting tanpa
                // gambar akan ditapis keluar selepas ini.
                'limit' => min($limit * 2, 100),
                'after' => $after,
            ]);

            $body = $response->json();

            $posts = collect($body['data'] ?? [])
                ->filter(fn (array $post) => filled($post['full_picture'] ?? null))
                ->map(fn (array $post) => $this->normalise($post))
                ->take($limit)
                ->values()
                ->all();

            return [
                'posts' => $posts,
                'next_cursor' => $body['paging']['cursors']['after'] ?? null,
            ];
        });
    }

    /**
     * Satu posting, untuk laluan fallback yang perlukan gambar dan teksnya.
     */
    public function find(string $postId): ?array
    {
        $response = $this->get($postId, [
            'fields' => 'id,message,full_picture,permalink_url,created_time',
        ]);

        $post = $response->json();

        return filled($post['id'] ?? null) ? $this->normalise($post) : null;
    }

    public function forgetCache(): void
    {
        Cache::forget(sprintf('dynoads:page_posts:%s:25:first', $this->page()));
    }

    /**
     * Ratakan bentuk bersarang Meta kepada sesuatu yang skrin boleh guna terus.
     *
     * Ringkasan reaksi dan komen datang sebagai {data: [], summary: {total_count}},
     * manakala share datang sebagai {count}. Ketiga-tiganya tiada sepenuhnya
     * apabila nilainya sifar, jadi setiap satu perlukan lalai.
     */
    private function normalise(array $post): array
    {
        $message = (string) ($post['message'] ?? '');

        return [
            'id' => $post['id'],
            'message' => $message,
            'excerpt' => str($message)->squish()->limit(110)->value(),
            'full_picture' => $post['full_picture'] ?? null,
            'permalink_url' => $post['permalink_url'] ?? null,
            'created_time' => $post['created_time'] ?? null,
            'reactions' => (int) ($post['reactions']['summary']['total_count'] ?? 0),
            'comments' => (int) ($post['comments']['summary']['total_count'] ?? 0),
            'shares' => (int) ($post['shares']['count'] ?? 0),
        ];
    }

    private function get(string $path, array $query): Response
    {
        $token = config('dynoads.access_token');

        if (blank($token)) {
            throw new RuntimeException('META_ACCESS_TOKEN tidak ditetapkan.');
        }

        $url = sprintf('%s/%s/%s', config('dynoads.graph_url'), config('dynoads.api_version'), $path);

        $response = Http::asJson()
            ->timeout(20)
            ->retry(2, 200, throw: false)
            ->get($url, array_filter($query + ['access_token' => $token], fn ($v) => $v !== null));

        if ($response->failed()) {
            throw new RuntimeException(
                'Gagal baca posting Page: '.($response->json('error.message') ?? $response->status())
            );
        }

        return $response;
    }
}
