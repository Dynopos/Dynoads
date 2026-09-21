<div class="mx-auto w-full max-w-md px-4 pb-28 pt-6">

    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
        Guna posting sedia ada
    </h1>
    <p class="mt-2 text-slate-500 dark:text-slate-400">
        Pilih {{ $this->minSelection() }}–{{ $this->maxSelection() }} posting yang anda dah ada.
        Kita bandingkan mana yang paling murah kosnya.
    </p>

    {{-- Amaran wajib. Posting yang naik secara organik diedar kepada peminat
         sedia ada; iklan diedar kepada orang asing. Dua benda berbeza. --}}
    <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
        Posting yang naik secara organik belum tentu murah kosnya sebagai iklan — sebab tu kita tetap split test.
    </p>

    @if ($error)
        <div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
            {{ $error }}
        </div>
    @endif

    @if ($result)
        <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            <p class="font-semibold">{{ $result['launched'] }} iklan siap, semua dalam keadaan PAUSED.</p>
            @if ($result['failed'] > 0)
                <p class="mt-1">{{ $result['failed'] }} gagal — semak timeline untuk sebabnya.</p>
            @endif
            @if ($result['fallbacks'] > 0)
                <p class="mt-1">
                    {{ $result['fallbacks'] }} posting terpaksa disalin jadi iklan baharu kerana Meta tolak butang
                    WhatsApp di atas posting asal. Like dan komen iklan tu tidak akan masuk ke posting asal anda.
                </p>
            @endif
            <a href="/run/{{ $result['ad_set_id'] }}" class="mt-2 inline-block font-semibold underline">
                Pergi ke skrin Run →
            </a>
        </div>
    @endif

    {{-- Grid posting --}}
    <div class="mt-6 grid grid-cols-2 gap-3">
        @forelse ($this->posts as $post)
            @php($selected = $this->isSelected($post['id']))
            <button
                type="button"
                wire:key="post-{{ $post['id'] }}"
                wire:click="toggle('{{ $post['id'] }}')"
                @class([
                    'relative overflow-hidden rounded-2xl border-2 text-left transition',
                    'border-indigo-500 ring-2 ring-indigo-200 dark:ring-indigo-900' => $selected,
                    'border-slate-200 dark:border-slate-700' => ! $selected,
                ])
            >
                <img src="{{ $post['full_picture'] }}" alt="" class="aspect-square w-full object-cover">

                @if ($selected)
                    <span class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                        ✓
                    </span>
                @endif

                <div class="space-y-1 p-2">
                    <p class="line-clamp-2 text-xs text-slate-700 dark:text-slate-300">
                        {{ $post['excerpt'] ?: 'Tiada teks' }}
                    </p>
                    <p class="text-[11px] text-slate-400">
                        {{ $post['reactions'] }} reaksi · {{ $post['comments'] }} komen
                    </p>
                    <p class="text-[11px] text-slate-400">
                        {{ \Illuminate\Support\Carbon::parse($post['created_time'])->diffForHumans() }}
                    </p>
                </div>
            </button>
        @empty
            @unless ($error)
                <p class="col-span-2 py-10 text-center text-sm text-slate-400">
                    Tiada posting bergambar dijumpai di Page ini.
                </p>
            @endunless
        @endforelse
    </div>

    @error('selected')
        <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
    @enderror

    {{-- Kawasan --}}
    <label class="mt-8 block text-sm font-semibold text-slate-900 dark:text-white">Kawasan</label>
    <select wire:model="geo" class="mt-2 w-full rounded-xl border-slate-200 py-3 dark:border-slate-700 dark:bg-slate-900">
        <option value="malaysia">Seluruh Malaysia</option>
        <option value="1888">Selangor</option>
        <option value="2542">Kuala Lumpur</option>
        <option value="1886">Johor</option>
        <option value="2543">Pulau Pinang</option>
    </select>

    {{-- Bajet --}}
    <label class="mt-6 block text-sm font-semibold text-slate-900 dark:text-white">Bajet sehari</label>
    <div class="mt-2 grid grid-cols-4 gap-2">
        @foreach ([2000, 3700, 5000, 10000] as $sen)
            <button
                type="button"
                wire:click="$set('dailyBudgetSen', {{ $sen }})"
                @class([
                    'rounded-xl border-2 py-3 text-sm font-semibold transition',
                    'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' => $dailyBudgetSen === $sen,
                    'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' => $dailyBudgetSen !== $sen,
                ])
            >
                RM{{ intdiv($sen, 100) }}
            </button>
        @endforeach
    </div>
    @error('dailyBudgetSen')
        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
    @enderror

    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
        {{ count($selected) }} posting dipilih · RM{{ number_format($dailyBudgetSen / 100, 0) }} sehari setiap satu
    </p>

    {{-- Satu skrin, satu keputusan --}}
    <div class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 p-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
        <button
            type="button"
            wire:click="launch"
            wire:loading.attr="disabled"
            @disabled($launching || count($selected) < $this->minSelection())
            class="mx-auto block w-full max-w-md rounded-2xl bg-indigo-600 py-4 text-base font-bold text-white disabled:opacity-40"
        >
            <span wire:loading.remove wire:target="launch">Buat iklan (PAUSED)</span>
            <span wire:loading wire:target="launch">Sedang buat…</span>
        </button>
        <p class="mx-auto mt-2 max-w-md text-center text-xs text-slate-400">
            Semua iklan dibuat dalam keadaan PAUSED. Tiada duit dibelanjakan sampai anda tekan RUN.
        </p>
    </div>
</div>
