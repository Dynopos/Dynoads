<?php

namespace App\Livewire\AdSets;

use App\Models\AdSet;
use App\Models\AdVariant;
use App\Services\AdLauncher;
use App\Services\PagePostService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * Skrin "Guna posting sedia ada".
 *
 * Peniaga sudah ada 50–200 posting di Page mereka. Sebahagiannya memang
 * sudah menjadi secara organik. Yang mereka tidak tahu ialah posting mana
 * yang layak dibelanjakan duit — dan itulah yang skrin ini jawab, bukan
 * dengan meneka, tetapi dengan split test.
 */
class ExistingPosts extends Component
{
    /** @var array<int, string> */
    public array $selected = [];

    public string $geo = 'malaysia';

    public int $dailyBudgetSen = 3700;

    public ?string $cursor = null;

    public ?string $error = null;

    public ?array $result = null;

    public bool $launching = false;

    public function mount(): void
    {
        $this->dailyBudgetSen = (int) config('dynoads.defaults.daily_budget_sen');
    }

    #[Computed]
    public function posts(): array
    {
        try {
            $page = app(PagePostService::class)->listPosts(
                limit: (int) config('dynoads.page_posts.per_page'),
                after: $this->cursor,
            );

            $this->error = null;

            return $page['posts'];
        } catch (Throwable $e) {
            $this->error = $e->getMessage();

            return [];
        }
    }

    public function toggle(string $postId): void
    {
        if (in_array($postId, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$postId]));

            return;
        }

        if (count($this->selected) >= $this->maxSelection()) {
            $this->addError('selected', "Pilih paling banyak {$this->maxSelection()} posting sahaja.");

            return;
        }

        $this->selected[] = $postId;
        $this->resetErrorBag('selected');
    }

    public function isSelected(string $postId): bool
    {
        return in_array($postId, $this->selected, true);
    }

    public function minSelection(): int
    {
        return (int) config('dynoads.page_posts.min_selection', 2);
    }

    public function maxSelection(): int
    {
        return (int) config('dynoads.page_posts.max_selection', 4);
    }

    public function loadMore(string $nextCursor): void
    {
        $this->cursor = $nextCursor;
        unset($this->posts);
    }

    public function launch(PagePostService $pagePosts, AdLauncher $launcher): void
    {
        if (count($this->selected) < $this->minSelection()) {
            $this->addError('selected', "Pilih sekurang-kurangnya {$this->minSelection()} posting supaya ada yang boleh dibandingkan.");

            return;
        }

        if ($this->dailyBudgetSen < 1000) {
            $this->addError('dailyBudgetSen', 'Bajet harian minimum RM10.');

            return;
        }

        $this->launching = true;

        try {
            $adSet = AdSet::create([
                'name' => 'Posting sedia ada — '.now()->format('d M Y'),
                'geo_json' => $this->geoPayload(),
                'daily_budget_sen' => $this->dailyBudgetSen,
                'status' => 'building',
            ]);

            foreach ($this->selected as $postId) {
                $post = $pagePosts->find($postId);

                AdVariant::create([
                    'ad_set_id' => $adSet->id,
                    'source_type' => AdVariant::SOURCE_EXISTING_POST,
                    'source_post_id' => $postId,
                    // Disimpan untuk laluan fallback sahaja. Bila laluan
                    // object_story_id berjaya, Meta yang menggunakan teks dan
                    // gambar posting asal, bukan salinan ini.
                    'caption' => $post['message'] ?? null,
                    'image_path' => $post['full_picture'] ?? null,
                    'status' => 'draft',
                ]);
            }

            $this->result = $launcher->launch($adSet->fresh());
            $this->result['ad_set_id'] = $adSet->id;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->launching = false;
        }
    }

    private function geoPayload(): array
    {
        return $this->geo === 'malaysia'
            ? ['countries' => ['MY']]
            : ['countries' => ['MY'], 'regions' => [['key' => $this->geo]]];
    }

    public function render()
    {
        return view('livewire.ad-sets.existing-posts');
    }
}
