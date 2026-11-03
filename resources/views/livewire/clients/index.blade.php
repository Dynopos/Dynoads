<div class="mx-auto w-full max-w-md px-4 pb-16 pt-6">

    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Pelanggan</h1>
    <p class="mt-2 text-slate-500 dark:text-slate-400">
        Peniaga yang iklannya kita urus. Bajet iklan dicaj terus ke kad mereka oleh Meta — kita tak pegang kad atau password.
    </p>

    @unless ($showForm)
        <button type="button" wire:click="$set('showForm', true)"
            class="mt-6 w-full rounded-2xl bg-indigo-600 py-4 text-base font-bold text-white">
            + Tambah pelanggan
        </button>
    @else
        <form wire:submit="save" class="mt-6 space-y-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <p class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                Pastikan pelanggan dah beri Partner Access, dan Page + akaun iklan dah di-assign kepada System User.
            </p>

            @foreach ([
                ['name', 'Nama kedai', 'Salon Kak Mah', 'text'],
                ['pageId', 'Page ID', '377330642350146', 'text'],
                ['adAccountId', 'ID akaun iklan', '701202512896650', 'text'],
                ['waPhone', 'Nombor WhatsApp (yang disambung pada Page)', '0187922844', 'tel'],
                ['contactName', 'Nama pemilik (pilihan)', 'Puan Mah', 'text'],
                ['contactPhone', 'Telefon pemilik (pilihan)', '0123456789', 'tel'],
            ] as [$field, $label, $placeholder, $type])
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</label>
                    <input type="{{ $type }}" wire:model="{{ $field }}" placeholder="{{ $placeholder }}"
                        class="mt-1 w-full rounded-xl border-slate-200 py-3 dark:border-slate-700 dark:bg-slate-900">
                    @error($field)
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="flex gap-2">
                <button type="button" wire:click="$set('showForm', false)"
                    class="flex-1 rounded-xl border-2 border-slate-200 py-3 font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    Batal
                </button>
                <button type="submit" class="flex-1 rounded-xl bg-indigo-600 py-3 font-bold text-white">Simpan</button>
            </div>
        </form>
    @endunless

    <div class="mt-6 space-y-3">
        @forelse ($this->clients as $client)
            <div wire:key="client-{{ $client->id }}" @class([
                'rounded-2xl border border-slate-200 p-4 dark:border-slate-700',
                'opacity-50' => ! $client->is_active,
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-900 dark:text-white">{{ $client->name }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $client->page_name ?? 'Page '.$client->page_id }} · {{ $client->ad_account_id }} · WA {{ $client->wa_phone }}
                        </p>
                        <p class="text-xs text-slate-400">{{ $client->ad_sets_count }} set iklan</p>
                    </div>
                    <button type="button" wire:click="toggleActive({{ $client->id }})" class="text-xs font-semibold text-slate-500 underline">
                        {{ $client->is_active ? 'Nyahaktif' : 'Aktifkan' }}
                    </button>
                </div>

                {{-- Langkah 1: akses --}}
                <div class="mt-3 flex items-center justify-between gap-2 text-sm">
                    <span @class(['text-emerald-700 dark:text-emerald-300' => $client->access_checked_at, 'text-slate-500' => ! $client->access_checked_at])>
                        {{ $client->access_checked_at ? '✓ Akses disahkan '.$client->access_checked_at->diffForHumans() : 'Akses belum disemak' }}
                    </span>
                    <button type="button" wire:click="checkAccess({{ $client->id }})" wire:loading.attr="disabled"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600">
                        Semak akses
                    </button>
                </div>
                @isset($checkResults[$client->id])
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ $checkResults[$client->id] }}</p>
                @endisset

                {{-- Langkah 2: persetujuan --}}
                @if ($client->consented_at)
                    <p class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">
                        ✓ Persetujuan {{ $client->consented_at->format('d M Y') }} — {{ $client->consent_note }}
                    </p>
                @else
                    <div class="mt-3">
                        <input type="text" wire:model="consentNotes.{{ $client->id }}"
                            placeholder='Bukti setuju, cth: "Balas Setuju di WA 3 Nov"'
                            class="w-full rounded-xl border-slate-200 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        @error("consentNotes.{$client->id}")
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <button type="button" wire:click="recordConsent({{ $client->id }})"
                            class="mt-2 w-full rounded-xl border-2 border-indigo-500 py-2 text-sm font-semibold text-indigo-700 dark:text-indigo-300">
                            Rekod persetujuan
                        </button>
                    </div>
                @endif

                @if (! $client->launchBlocker())
                    <a href="/posting-sedia-ada?client={{ $client->id }}" class="mt-3 block text-sm font-semibold text-indigo-600 underline">
                        Buat iklan untuk {{ $client->name }} →
                    </a>
                @endif
            </div>
        @empty
            <p class="py-10 text-center text-sm text-slate-400">Belum ada pelanggan.</p>
        @endforelse
    </div>
</div>
