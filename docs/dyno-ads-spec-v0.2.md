# DYNO ADS — Spec Produk (v0.2)
**Dari satu ayat peniaga → poster siap → iklan jalan → app pantau sendiri → report masuk WhatsApp**

Disediakan: 2 Sept 2026 · Pemilik: Borhan Sidqy (DYNOPRO) · Menggantikan v0.1
Status: draf kerja untuk Claude Code

---

## 0. Apa yang berubah dari v0.1

v0.1 = borang 3 skrin, user upload gambar sendiri.
v0.2 = **auto-pilot penuh**, empat modul baru:

| Baru | Kenapa |
|---|---|
| **Chat AI team** ganti borang | Peniaga tak reti isi borang iklan. Dia reti bercerita. |
| **Enjin poster** (AI latar + teks HTML) | Peniaga tiada gambar cantik. Ini halangan #1 dia tak buat ads. |
| **Auto posting organik** (Page + IG) | Bukan semua peniaga sanggup bayar ads hari pertama. Posting percuma jadi pintu masuk. |
| **Monitor + report harian** | Ads yang tiada orang jaga = duit bocor. Ini nilai sebenar yang dijual. |
| **Guna posting sedia ada** | Ramai peniaga dah ada posting di Page. Mereka tak perlu creative baru — mereka perlu tahu posting mana yang layak dibelanjakan duit. |

Fasa 0 (Create Post — borang, campaign PAUSED, Run, dashboard) **sudah siap**.
Semua di bawah ni dibina **di atas** kod tu, bukan ganti.

---

## 1. Posisi produk

**"Premium Apps. Harga Kaki Lima."**

Kita tak lawan agensi. Kita lawan *keadaan peniaga tak buat ads langsung* sebab:
1. Tak reti Ads Manager
2. Tiada gambar cantik
3. Tak tahu nak tulis apa
4. Buat sekali, tak jadi, terus give up

Empat halangan tu = empat modul kita.

### Nota penting tentang pesaing

Rujukan yang Bob tunjuk (FunnelEvo / Copypen) sudah ada di pasaran dengan flow chat + AI team + 4 creative + auto pantau. Ini mengesahkan pasaran wujud — tapi maknanya:

- **Jangan tiru nama/persona/UI mereka.** Nama watak AI, susunan skrin, ayat pemasaran mereka adalah aset jenama mereka. Guna identiti Dyno sendiri.
- Kelebihan kita mesti sesuatu yang mereka tiada. Cadangan: **integrasi DynoPOS** (app tahu produk & jualan sebenar peniaga → caption dan sasaran lebih tepat), dan **posting organik percuma** sebagai pintu masuk sebelum bayar ads.
- Avatar AI: guna ilustrasi (siri dino), **bukan** gambar muka manusia stok. Elak isu rupa orang sebenar dan elak nampak tiruan.

### Kru AI Dyno (nama kerja)

| Watak | Peranan | Buat apa |
|---|---|---|
| **AI Amir** | Ketua Projek | Tanya soalan, kumpul maklumat, sahkan sebelum lancar |
| **AI Nurin** | Penulis Copy | Caption + headline (formula takut → tenang) |
| **AI Zaki** | Pereka | Poster: pilih template, jana latar, susun teks |
| **AI Hana** | Penganalisis | Pantau prestasi, cadang pause/scale, tulis report harian |

Secara teknikal semuanya satu model dengan **system prompt berbeza** — bukan empat servis berasingan. Watak wujud untuk UI, bukan untuk seni bina.

---

## 2. Seni bina keseluruhan

```
                 ┌──────────────────────────────────┐
   Peniaga ────► │  CHAT (AI Amir)                  │
   "nak iklan    │  slot-filling + tool calling     │
    sambal,      └───────────┬──────────────────────┘
    RM30 sehari"             │ isi medan (produk, tawaran,
                             │ destinasi, bajet, tempoh, kawasan)
                             ▼
                 ┌──────────────────────────────────┐
                 │  CampaignBrief (satu jadual)     │
                 │  — sumber kebenaran tunggal      │
                 └───┬──────────────┬───────────────┘
                     │              │
        ┌────────────▼───┐   ┌──────▼────────────┐
        │ CaptionService │   │ PosterService     │
        │ (AI Nurin)     │   │ (AI Zaki)         │
        │ caption+headline│   │ latar AI + teks  │
        └────────────┬───┘   │ HTML → PNG 1080   │
                     │       └──────┬────────────┘
                     └───────┬──────┘
                             ▼
                 ┌──────────────────────────────────┐
                 │  4 creative → user SAHKAN        │
                 └───────────┬──────────────────────┘
                             ▼
             ┌───────────────┴────────────────┐
             ▼                                ▼
   ┌──────────────────┐            ┌────────────────────┐
   │ MetaAdsService   │            │ PagePostService    │
   │ campaign PAUSED  │            │ posting organik    │
   │ → RUN            │            │ FB Page + IG       │
   └────────┬─────────┘            └────────────────────┘
            ▼
   ┌──────────────────────────────────────────┐
   │ SyncMetrics (30–60 min)                  │
   │ SplitTestService (keputusan di ambang)   │  ← AI Hana
   │ DailyReport → WhatsApp 8 pagi            │
   └──────────────────────────────────────────┘
```

**Peraturan seni bina yang tidak boleh dilanggar:**

1. LLM **tidak pernah** panggil Meta API terus. LLM hanya isi medan `CampaignBrief`. App yang validate dan panggil Meta.
2. Tiada duit dibelanjakan tanpa **satu klik pengesahan manusia** yang jelas.
3. App tak pernah edit campaign sedia ada — sentiasa buat baru (kekal dari v0.1).
4. App tak pernah sentuh campaign yang bukan ia buat.

Peraturan #1 penting: itu beza antara app yang boleh dipercayai dengan app yang boleh bakar RM900 sebab LLM salah faham satu ayat.

---

## 3. Modul A — Chat AI Team

### Cara ia jalan (bukan chatbot bebas)

Ini **slot-filling berpandu**, bukan perbualan terbuka. Ada senarai medan yang mesti diisi. AI Amir tanya satu demi satu, satu soalan satu mesej, dan tawarkan butang pilihan bila boleh (7 hari / 14 hari / 30 hari) supaya user boleh tekan, bukan menaip.

**Medan wajib (`campaign_briefs`):**

| Medan | Contoh | Cara dapat |
|---|---|---|
| `objective` | leads / mesej / kenalan | Soalan pertama |
| `business_name` | Salon Shah Alam | Chat, atau auto dari DynoPOS |
| `product_offer` | Potong rambut, facial, nail. Diskaun pelanggan baru | Chat |
| `destination` | WhatsApp / borang / telefon | Chat + pilihan |
| `destination_value` | 60187922844 atau URL borang | Chat |
| `geo` | Shah Alam / seluruh Malaysia | Chat + peta pilihan |
| `daily_budget_sen` | 3000 | Chat + butang RM20/30/50 |
| `duration_days` | 30 | Butang 7/14/30 |
| `brand_assets` | logo, warna, gambar kedai | Upload (boleh skip) |

**Kiraan had belanja mesti dipapar sebelum lancar:** `bajet × hari = had maksimum`, dan had tu dikuatkuasakan di Meta (campaign `lifetime` atau tarikh tamat), bukan sekadar ayat di skrin. Kalau kita tulis "RM900 maksimum", Meta kena benar-benar berhenti pada RM900.

### Teknikal

- Jadual: `conversations`, `messages`, `campaign_briefs`
- Claude API dengan **tool use**. Tools yang dibenarkan: `set_field`, `ask_user`, `request_upload`, `propose_creatives`, `request_confirmation`. **Tiada** tool yang memanggil Meta.
- Setiap balasan LLM disahkan terhadap skema. Nilai yang gagal validate → AI tanya semula, bukan diteka.
- Ada butang **"Guna borang biasa"** sentiasa ada. Kalau chat tersekat, user tak terperangkap.
- Simpan setiap mesej. Ini data emas untuk baiki prompt kemudian.

### Ayat amaran wajib

Di bawah kotak chat, sentiasa: *"AI boleh buat silap — anda sahkan sendiri sebelum apa-apa duit dibelanjakan."*
Bukan sekadar undang-undang. Ia yang buat user percaya bila AI betul-betul silap sekali dua.

---

## 4. Modul B — Enjin Poster (AI latar + teks HTML)

Ini modul paling bernilai dan paling banyak orang buat salah.

### Kenapa hibrid, bukan AI penuh

Model imej masih kerap rosakkan **teks Bahasa Melayu** — huruf tersalah, ejaan pelik, jenama tertulis salah. Poster iklan yang tersalah eja nama kedai = memalukan peniaga.

Jadi:

```
[ AI jana LATAR sahaja — tiada teks langsung dalam prompt ]
                    +
[ Template HTML letak SEMUA teks di atasnya ]
                    ↓
[ Playwright render → PNG 1080×1080 ]
```

Teks sentiasa tajam, sentiasa betul ejaan, sentiasa ikut warna jenama. Latar nampak macam AI. Dapat dua-dua.

### Sumber latar (ikut keutamaan)

1. **Gambar peniaga sendiri** — sentiasa terbaik. Kedai betul, orang betul, makanan betul.
2. **AI latar abstrak/produk** — tekstur, bahan mentah, suasana, produk atas meja.
3. **AI latar dengan orang** — guna berhati-hati. Untuk perniagaan sebenar (salon, klinik), gambar model AI boleh mengelirukan pelanggan yang sangka itu kerja sebenar kedai tu. Cadangan: benarkan, tapi papar amaran satu baris dan label dalam app "gambar ilustrasi".

### Template poster (mula dengan 6)

| Slug | Bila guna | Medan teks |
|---|---|---|
| `promo-meletup` | Diskaun, tawaran masa terhad | headline, promo, senarai 3 servis, CTA |
| `harga-jelas` | Produk berharga tetap | nama produk, harga, harga asal, CTA |
| `sebelum-selepas` | Salon, bengkel, kecantikan | 2 gambar, label kiri/kanan, headline |
| `senarai-servis` | Kedai banyak servis | headline, 4 baris servis, lokasi |
| `testimoni` | Bukti sosial | petikan, nama pelanggan, rating |
| `kedai-baru` | Bukaan, lokasi baru | nama kedai, alamat, waktu buka |

Setiap template:
- Blade + Tailwind, saiz tetap 1080×1080 (dan varian 1080×1350, 1080×1920 untuk Story/Reels)
- **Auto-fit teks**: had aksara per slot + kecilkan saiz font automatik kalau melimpah. Tiada teks terpotong, selamanya.
- Token jenama: `--brand-primary`, `--brand-dark`, logo, font. Datang dari `brand_kits` milik user.
- Lapisan gelap (scrim) di belakang teks supaya kontras cukup walau latar terang.

### Teknikal

```
App\Services\Poster\PosterService
    ->render(string $template, array $data, ?string $backgroundPath): string  // path PNG

App\Services\Poster\Backgrounds\BackgroundDriver (interface)
    - UploadDriver     gambar user
    - AiDriver         jana latar (prompt: TIADA teks, TIADA logo)
    - StockDriver      simpanan dalaman ikut industri
```

- Render: skrip Node + Playwright (Chromium sudah ada), `page.setViewportSize(1080,1080)` → `screenshot()`.
- Cache: kunci = hash(template + data + background). Poster sama tak dijana dua kali.
- Semua poster disimpan `storage/app/public/posters/{brief}/{n}.png`, kemudian ke Meta sebagai `image_hash`.

### Kos & kuota (kena diputuskan awal)

Latar AI ada kos per imej. Kalau tak dihadkan, seorang user boleh jana 200 poster dan margin RM39 lenyap.
Cadangan: **kuota latar AI per pelan** (Percuma 5/bulan, Peniaga 40/bulan, Pro 150/bulan). Template + gambar sendiri = tanpa had, sebab percuma.

---

## 4B. Modul B2 — Guna posting sedia ada

**Ini laluan terpantas kepada nilai, dan ia tidak perlukan poster, caption, atau permission baru.**

Ramai peniaga sudah ada 50–200 posting di Page mereka. Sebahagian posting tu memang
sudah "menjadi" secara organik. Yang mereka tak tahu: posting mana yang patut dibelanjakan duit.

### Flow

```
[1] Pilih sumber creative
    ├─ Poster baru (Modul B)
    └─ Posting sedia ada  ◄── modul ni
[2] App senaraikan posting Page (gambar + engagement + tarikh)
[3] Peniaga tick 2–4 posting
[4] Setiap posting → satu campaign (setting terbukti yang sama)
[5] RUN → split test → pemenang di-scale
```

### Teknikal

- Senarai posting: `GET /{page-id}/posts` (atau `/published_posts`) — `id`, `message`,
  `full_picture`, `created_time`, `shares`, `reactions.summary(true)`, `comments.summary(true)`.
  Tapis kepada posting bergambar sahaja untuk Fasa 1.
- Creative guna `object_story_id = "{page_id}_{post_id}"` — **bukan** `object_story_spec`.
- CTA WhatsApp di atas posting sedia ada: hantar `call_to_action` bersama `object_story_id`.
  **Ini perlu disahkan pada akaun sebenar dahulu** — sesetengah gabungan objektif/format
  menolaknya. Kalau ditolak, jalan kedua ialah salin gambar + teks posting menjadi creative
  baru (dark post). Bina kedua-dua laluan; cuba yang pertama, jatuh ke yang kedua secara automatik
  dan beritahu user apa yang berlaku.
- Permission: `pages_show_list` + `pages_read_engagement` — **kedua-duanya sudah ada dalam
  App Review pusingan 1.** Tiada permission tambahan.

### Kenapa ni penting (dan satu amaran)

**Kelebihan:** bila `object_story_id` diguna, semua like, komen dan share terkumpul pada
posting asal. Peniaga nampak posting dia sendiri naik — bukan iklan asing. Bukti sosial
terkumpul merentas semua campaign, bukan berpecah.

**Amaran yang mesti dipapar:** posting yang viral secara organik **bukan** jaminan iklan yang
murah kosnya. Organik diedar kepada peminat sedia ada; iklan diedar kepada orang asing.
Sebab tu ia tetap kena split test — bukan terus scale posting paling banyak like.

### Peraturan

1. App **tidak pernah** edit, padam atau ubah posting sedia ada. Ia hanya merujuk `post_id`.
2. App **tidak pernah** senaraikan atau sentuh posting Page yang user tak sambungkan.
3. Rekod `source_type` (`poster` / `existing_post`) dalam `ad_variants` supaya laporan boleh
   bandingkan prestasi dua sumber tu — dalam beberapa bulan, data ni yang beritahu peniaga
   sama ada berbaloi bayar untuk poster baru.

---

## 5. Modul C — Auto posting organik

Pintu masuk untuk peniaga yang belum sanggup bayar ads.

- Jadual kandungan: user pilih berapa kali seminggu (2 / 3 / 5), app cadang tema mingguan
- Setiap posting: poster (Modul B) + caption (AI Nurin, gaya organik bukan gaya iklan) + hashtag
- User approve dalam satu skrin senarai — swipe/tick, bukan satu-satu
- Terbit: FB Page `POST /{page-id}/photos`, IG melalui Content Publishing API (`/media` → `/media_publish`)
- Scheduler Laravel; simpan `scheduled_posts` dengan status draft/approved/published/failed

**Permission tambahan diperlukan:** `pages_manage_posts`, `instagram_basic`, `instagram_content_publish`.
Ini menjadikan App Review lebih berat (lihat §8). Sebab tu modul ni diletak **selepas** ads berjalan — bukan sebab teknikalnya susah, tapi sebab kos kelulusan.

---

## 6. Modul D — Auto split test, monitor & report

### Split test (kekal dari v0.1, diperkemas)

- Fasa 1 Launch: 1 creative = 1 campaign, semua PAUSED → Run serentak
- Fasa 2 Penilaian: hari ke-3 **atau** bila setiap campaign spend ≥ RM60
  - Metrik utama: kos per lead WhatsApp (`onsite_conversion.total_messaging_connection`)
  - Pemenang = 2 terbaik dengan ≥ 3 lead; data tak cukup → tunggu 1 hari, maksimum 2 kali, lepas tu pilih ikut CTR
- Fasa 3 Scale: **campaign BARU** untuk pemenang (bukan edit), bajet sama atau ×1.5, campaign asal dipause selepas yang baru ACTIVE

### Monitor — jujur tentang "setiap 30 minit"

Pesaing iklan "pantau setiap 30 minit". Kita boleh sync sekerap tu untuk **paparan**, tapi **jangan buat keputusan** sekerap tu. Sebabnya:

- Data insights Meta sendiri lag ~15 minit hingga sejam
- 30 minit pada bajet RM30/hari ≈ RM0.60 dibelanjakan. Tiada apa yang bermakna secara statistik pada RM0.60.
- Pause terlalu awal membunuh iklan yang sebenarnya bagus, dan reset pembelajaran algoritma

**Dasar kita:**

| Semakan | Kekerapan | Tindakan |
|---|---|---|
| Sync metrik | 30 minit | Papar sahaja. Tiada tindakan. |
| Guard pembaziran | Setiap sync | Pause hanya jika: spend ≥ RM40 **dan** 0 lead **dan** CTR < 0.5% |
| Penilaian penuh | Hari ke-3 / spend ≥ RM60 | Pilih pemenang, pause yang lemah, scale |

Ini boleh diiklankan dengan jujur sebagai *"dipantau setiap 30 minit, tindakan hanya bila data cukup"* — dan ia sebenarnya melindungi duit user lebih baik daripada pause pantas.

### Report harian WhatsApp

- Template utility `dynoads_daily_report`, hantar 8:00 pagi Asia/Kuala_Lumpur
- Kandungan: belanja semalam, lead masuk, kos/lead, iklan terbaik, apa yang app buat semalam, dan **satu cadangan**
- Guna skill `whatsapp-cloud-api`. Kos ~RM0.12/mesej → ~RM3.60/user/bulan
- Kalau ada tindakan besar (pause / campaign baru), hantar notifikasi segera, bukan tunggu pagi

---

## 7. Model data (tambahan kepada v0.1)

```
brand_kits          user_id, logo_path, primary_hex, dark_hex, font, tone
conversations       user_id, status(active|done|abandoned), brief_id
messages            conversation_id, role(user|ai), persona, content, tool_calls_json
campaign_briefs     user_id, objective, business_name, product_offer, destination,
                    destination_value, geo_json, daily_budget_sen, duration_days,
                    max_spend_sen, status(draft|confirmed|launched)
poster_jobs         brief_id, template, data_json, background_source(upload|ai|stock),
                    background_path, output_path, cache_key, status
scheduled_posts     user_id, poster_job_id, caption, platform(fb|ig), publish_at,
                    status(draft|approved|published|failed), external_id
ai_usage            user_id, kind(caption|chat|image), tokens_or_count, cost_sen, created_at
```

`ai_usage` bukan hiasan — tanpa ia, mustahil nak tahu pelan mana yang rugi.

Jadual v0.1 kekal: `ad_sets`, `ad_variants`, `metrics_daily`, `auto_actions`.
`ad_sets` dapat lajur baru `brief_id` untuk sambung ke chat.
`ad_variants` dapat `source_type` (poster | existing_post) dan `source_post_id`.

---

## 8. Meta App Review — realiti baru

v0.1 perlukan 6 permission. v0.2 perlukan 9:

`ads_management`, `ads_read`, `business_management`, `pages_show_list`,
`pages_read_engagement`, `pages_manage_ads`, `pages_manage_posts`,
`instagram_basic`, `instagram_content_publish`

Lebih banyak permission = lebih lama, lebih kerap ditolak.

**Strategi: hantar dua pusingan.**

| Pusingan | Permission | Bila |
|---|---|---|
| 1 | 6 permission ads sahaja | Sebaik chat + ads boleh demo hujung ke hujung |
| 2 | 3 permission posting | Selepas pusingan 1 lulus dan app dah ada user sebenar |

Sebab: app yang sudah lulus sekali dan ada penggunaan sebenar jauh lebih senang lulus kali kedua.

**Mula sekarang, jangan tunggu kod siap:**
- Business Verification (SSM, alamat, telefon bisnes) — ambil masa berminggu, tiada kaitan dengan kod
- Privacy Policy + Terms URL (BM & EN) di dynopro.my
- Data Deletion Callback endpoint

---

## 9. Harga (dikemas kini untuk kos AI)

| Pelan | Harga | Had |
|---|---|---|
| Percuma | RM0 | 1 campaign, 2 creative, 5 latar AI/bulan, report mingguan, tiada auto-scale |
| Peniaga | RM49/bulan | 3 campaign aktif, 4 creative, 40 latar AI/bulan, auto split test + scale, report harian WhatsApp, posting organik 12/bulan |
| Pro | RM129/bulan | Tanpa had campaign, 150 latar AI/bulan, 2 ad account, posting tanpa had, priority support |

Naik dari RM39/RM99 sebab v0.2 tanggung kos imej AI + chat. Anggaran kos sebenar per user Peniaga:
WhatsApp ~RM3.60 + Claude chat ~RM2 + latar AI 40 keping ~RM6–12 + hosting dikongsi ≈ **RM12–18**.
Margin masih sihat pada RM49; pada RM39 ia jadi nipis bila user guna kuota penuh.

Bajet ads tetap dibayar user terus kepada Meta. App tak pegang duit ads.

---

## 10. Roadmap

| Fasa | Apa | Anggaran |
|---|---|---|
| **0 ✅** | Create Post — borang, campaign PAUSED, Run, dashboard | Siap |
| **1** | Guna posting sedia ada: senarai posting Page, pilih 2–4, split test | 2–3 hari |
| **2** | Enjin poster: 6 template, auto-fit teks, Playwright render, brand kit | 1–2 minggu |
| **3** | Latar AI: driver, kuota, cache, label "gambar ilustrasi" | 4–5 hari |
| **4** | Chat AI team: slot-filling, tool use, skrin sahkan, fallback borang | 1.5–2 minggu |
| **5** | Auto split test + guard pembaziran + timeline | 1 minggu |
| **6** | Report WhatsApp harian + notifikasi tindakan | 4–5 hari |
| **7** | Multi-user: auth, FB OAuth, PDPA → **App Review pusingan 1** | 2 minggu + tunggu |
| **8** | Jual: FPX, kuota pelan, landing page, onboarding | 1 minggu |
| **9** | Posting organik FB + IG → **App Review pusingan 2** | 1.5 minggu + tunggu |

Business Verification dimulakan **minggu ini**, selari dengan Fasa 1.

Kenapa posting sedia ada didahulukan: 2–3 hari kerja, guna semula seluruh Fasa 0, tiada permission baru, dan Bob boleh terus split test posting DynoPOS sendiri minggu ni.

Kenapa poster didahulukan daripada chat: poster boleh dijual bersendirian (peniaga bayar untuk poster cantik walaupun tak buat ads), dan chat tanpa poster cuma borang yang lebih lambat.

---

## 11. Risiko

| Risiko | Mitigasi |
|---|---|
| LLM salah faham → campaign salah | LLM tak sentuh Meta. Pengesahan manusia wajib. Had belanja dikuatkuasakan di Meta. |
| Teks Melayu rosak atas poster | Teks tak pernah dijana AI — sentiasa HTML. Ini keputusan seni bina, bukan tetapan. |
| Kos imej AI makan margin | Kuota per pelan + cache + `ai_usage` dijejak dari hari pertama |
| App Review ditolak berulang | Hantar dua pusingan, ads dulu. Screencast jelas. Minta permission minimum setiap pusingan. |
| Muka AI dalam iklan perniagaan sebenar | Label "gambar ilustrasi", galak gambar sendiri, amaran satu baris masa pilih |
| User sangka "auto = pasti untung" | Onboarding: app optimize, tak jamin lead. Papar benchmark kos/lead industri. |
| Pesaing sudah ada di pasaran | Jangan tiru. Beza pada integrasi DynoPOS + posting organik percuma + harga. |
| Token FB luput senyap | Peringatan WhatsApp 7 hari awal + banner dashboard + auto-action berhenti dengan elok |

---

*Rujukan dalaman: split test manual 2 Sept 2026 — akaun 701202512896650, page 377330642350146. Fasa 0 dibina 2 Sept 2026.*
