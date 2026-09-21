# Fasa 1 — nota integrasi

Kod Fasa 1 dalam branch ini ditulis **tanpa akses kepada kod dynoads.my sebenar**.
Repo `Dynopos/Dynoads` kosong sepenuhnya dan `Dynopos/Adsbaru` hanya mengandungi
dua fail PNG, jadi tiada kod Fasa 0 untuk dibina di atasnya.

Nota ini menerangkan apa yang sebenar, apa yang andaian, dan apa yang perlu
diperiksa semasa menggabungkannya ke dalam app sebenar.

## Status ujian

```
vendor/bin/pest          16 passed (104 assertions)
vendor/bin/pint --test   passed
```

Ujian dijalankan terhadap skeleton Laravel 11 tempatan dengan `Http::fake()`.
Tiada panggilan sebenar ke Meta dibuat.

---

## Deliverable sebenar Fasa 1

Fail-fail ini memang baharu dan sepatutnya boleh digugurkan terus ke dalam app.

| Fail | Apa | Risiko merge |
|---|---|---|
| `app/Services/PagePostService.php` | Senarai posting Page, baca sahaja, cache 10 minit, paging cursor | Rendah — kelas baharu |
| `database/migrations/2026_09_21_000500_add_source_to_ad_variants_table.php` | Tambah `source_type` + `source_post_id` pada `ad_variants` | Rendah — semak nama jadual anda betul `ad_variants` |
| `app/Livewire/AdSets/ExistingPosts.php` + view | Skrin pilih 2–4 posting, kawasan, bajet | Sederhana — ikut konvensyen layout anda |
| `tests/` (3 fail) | Ujian Pest untuk semua di atas | Rendah |

`MetaAdsService::createAdFromPost()` ialah **method baharu** yang perlu disalin
ke dalam `MetaAdsService` sedia ada anda, bersama pembantunya
(`composeObjectStoryId`, `isCtaRejection`, `createAdByCopyingPost`,
`uploadImageFromUrl`) dan kelas `MetaApiException`.

---

## Andaian yang perlu disemak

Fail berikut ditulis semata-mata supaya suite ujian ada sesuatu untuk dijalankan.
**Padam semuanya semasa merge** dan gunakan versi sebenar anda.

- `database/migrations/2026_09_01_*` — keempat-empat jadual Fasa 0
- `app/Models/{AdSet,AdVariant,AutoAction,MetricDaily}.php`
- Method Fasa 0 dalam `MetaAdsService` (`uploadImage`, `createCampaign`,
  `createAdSet`, `createAd`, `enable`, `pause`, `getInsights`)
- `app/Services/AdLauncher.php`
- `config/dynoads.php`

Perkara khusus yang paling mungkin berbeza daripada app sebenar:

1. **Nama lajur bajet.** Saya guna `daily_budget_sen` (integer, sen). Kalau app
   anda guna `daily_budget` dalam ringgit, migration dan `AdLauncher` perlu diselaraskan.
2. **Bentuk `MetaApiException`.** Kalau `MetaAdsService` anda sudah membuang jenis
   exception lain, `isCtaRejection()` perlu membaca `code`/`error_subcode` daripada
   jenis itu. Logik pengesanan sendiri tidak berubah.
3. **Tandatangan `AdLauncher::launch()`.** Saya andaikan ia menerima `AdSet` dan
   mengulang `$adSet->variants`. Kalau milik anda berbeza, hanya cabang
   `isFromExistingPost()` yang perlu dipindahkan.
4. **`config/dynoads.php`.** Nilai di dalamnya disalin daripada CLAUDE.md. Kalau
   fail sebenar anda sudah ada, ambil hanya blok `page_posts` dan `cta_rejection`.

---

## Subkod penolakan CTA

`config/dynoads.php` menyenaraikan subkod Meta yang bermaksud "call_to_action
tidak diterima di atas object_story_id":

```php
'subcodes' => [1487831, 1885183, 2490408],
```

Meta tidak mendokumenkan satu kod tunggal untuk keadaan ini, jadi pengesanan
juga memadankan corak teks mesej sebagai jaring keselamatan. **Senarai ini perlu
disahkan terhadap akaun sebenar** — jalankan sekali pada akaun 701202512896650
dengan satu posting, dan kalau laluan `object_story_id` ditolak dengan subkod
yang tiada dalam senarai, tambahkannya.

Sehingga pengesahan itu dibuat, corak teks yang menangkapnya, dan fallback tetap
berfungsi — cuma laluan pengesanan kurang tepat.

---

## Perkara yang sengaja tiada

- **Tiada method tulis dalam `PagePostService`.** Peraturan 13. Ada ujian yang
  membaca kod sumber kelas itu dan gagal kalau sesiapa menambah `Http::post`,
  `->delete(` atau method bernama `create*`/`update*`/`publish*`.
- **Tiada method `update*` dalam `MetaAdsService`.** Peraturan mutlak #1.
- Semua campaign, adset dan ad dicipta `PAUSED`. Peraturan mutlak #3.
