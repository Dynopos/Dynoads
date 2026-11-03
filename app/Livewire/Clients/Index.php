<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Services\MetaAdsService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * Skrin "Pelanggan" — senarai peniaga yang iklannya kita urus.
 *
 * Aliran sebenar di kedai pelanggan:
 *   1. Pelanggan beri Partner Access → kita Assign Assets kepada System User
 *   2. Tambah pelanggan di sini (Page ID, akaun iklan, nombor WhatsApp)
 *   3. Tekan "Semak akses" — buktikan System User memang nampak aset tu
 *   4. Rekod persetujuan bertulis — baru boleh buat iklan
 */
class Index extends Component
{
    public string $name = '';

    public string $contactName = '';

    public string $contactPhone = '';

    public string $pageId = '';

    public string $adAccountId = '';

    public string $waPhone = '';

    /** @var array<int, string> mesej keputusan "Semak akses", ikut client id */
    public array $checkResults = [];

    /** @var array<int, string> */
    public array $consentNotes = [];

    public bool $showForm = false;

    #[Computed]
    public function clients()
    {
        return Client::query()->withCount('adSets')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->pageId = preg_replace('/\D/', '', $this->pageId);
        $this->adAccountId = (string) Client::normaliseAdAccountId($this->adAccountId);
        $this->waPhone = (string) Client::normalisePhone($this->waPhone);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'contactName' => ['nullable', 'string', 'max:120'],
            'contactPhone' => ['nullable', 'string', 'max:30'],
            'pageId' => ['required', 'regex:/^\d{5,20}$/'],
            'adAccountId' => [
                'required', 'regex:/^act_\d{5,20}$/',
                Rule::unique('clients', 'ad_account_id')->where('page_id', $this->pageId),
            ],
            'waPhone' => ['required', 'regex:/^60\d{8,11}$/'],
        ], [
            'name.required' => 'Nama kedai wajib diisi.',
            'pageId.required' => 'Page ID wajib diisi.',
            'pageId.regex' => 'Page ID mesti nombor sahaja (lihat Page → About → Page transparency).',
            'adAccountId.required' => 'ID akaun iklan wajib diisi.',
            'adAccountId.regex' => 'ID akaun iklan tak sah. Contoh: 701202512896650.',
            'adAccountId.unique' => 'Pelanggan dengan Page dan akaun iklan ni dah wujud.',
            'waPhone.required' => 'Nombor WhatsApp wajib diisi.',
            'waPhone.regex' => 'Nombor WhatsApp tak sah. Contoh: 0123456789 atau 60123456789.',
        ]);

        Client::create([
            'name' => $data['name'],
            'contact_name' => $data['contactName'] ?: null,
            'contact_phone' => $data['contactPhone'] ?: null,
            'page_id' => $data['pageId'],
            'ad_account_id' => $data['adAccountId'],
            'wa_phone' => $data['waPhone'],
        ]);

        $this->reset('name', 'contactName', 'contactPhone', 'pageId', 'adAccountId', 'waPhone', 'showForm');
        unset($this->clients);
    }

    public function checkAccess(int $clientId, MetaAdsService $meta): void
    {
        $client = Client::findOrFail($clientId);

        try {
            $result = $meta->forClient($client)->checkAccess();

            $client->update([
                'page_name' => $result['page_name'],
                'ad_account_name' => $result['ad_account_name'],
                'access_checked_at' => now(),
            ]);

            $this->checkResults[$clientId] = $result['account_status'] === 1
                ? "✓ Akses OK: {$result['page_name']} · {$result['ad_account_name']}"
                : "Akses OK, tapi akaun iklan tak aktif (status {$result['account_status']}). Minta pelanggan semak Billing / Account Quality.";
        } catch (Throwable $e) {
            $client->update(['access_checked_at' => null]);
            $this->checkResults[$clientId] = $e->getMessage();
        }

        unset($this->clients);
    }

    public function recordConsent(int $clientId): void
    {
        $note = trim($this->consentNotes[$clientId] ?? '');

        if ($note === '') {
            $this->addError("consentNotes.{$clientId}", 'Tulis bukti persetujuan, contohnya: "Balas Setuju di WhatsApp 3 Nov 2026".');

            return;
        }

        Client::whereKey($clientId)->update([
            'consented_at' => now(),
            'consent_note' => $note,
        ]);

        unset($this->consentNotes[$clientId], $this->clients);
    }

    public function toggleActive(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        $client->update(['is_active' => ! $client->is_active]);
        unset($this->clients);
    }

    public function render()
    {
        return view('livewire.clients.index');
    }
}
