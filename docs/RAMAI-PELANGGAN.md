# Ramai pelanggan (servis urus iklan)

App ni boleh urus iklan beberapa peniaga dari satu tempat. Ia **tidak**
memerlukan login Facebook pelanggan, token pelanggan atau kad bank pelanggan.

## Cara ia berfungsi

```
Pelanggan                         DynoPOS Business Manager           App
─────────                         ────────────────────────           ───
Partner Access (Page + ad acct) → Assign Assets → System User  →  satu token dalam .env
Kad bank dalam ad account sendiri                                  menyasarkan aset pelanggan
```

- `clients` simpan Page ID, ad account ID dan nombor WhatsApp setiap pelanggan.
  **Tiada token** dalam jadual ini.
- `ad_sets.client_id` null = akaun DynoPOS sendiri (nilai `.env`), sama seperti sebelum ini.
- `MetaAdsService::forClient($client)` pulangkan salinan service yang menyasarkan
  aset pelanggan. Service asal tidak berubah.
- `AdLauncher::launch()` guna pelanggan ad set secara automatik.

## Pengawal

- Iklan pelanggan **tidak boleh dibuat** selagi persetujuan bertulis belum direkod
  (`clients.consented_at`), atau pelanggan dinyahaktifkan. Ditolak sebelum satu pun
  panggilan ke Meta.
- "Semak akses" hanya membuat GET ke Page dan ad account untuk membuktikan
  System User memang nampak aset tu sebelum iklan dibuat.
- Setiap `auto_actions` jenis `launch` merekod `client_id` dan `ad_account_id`.
- Pelanggan yang ada sejarah iklan tidak boleh dipadam (foreign key `restrict`).
  Nyahaktifkan sahaja.

## Skrin

| Komponen | Route dicadangkan |
|---|---|
| `App\Livewire\Clients\Index` | `/pelanggan` |
| `App\Livewire\AdSets\ExistingPosts` | `/posting-sedia-ada?client={id}` (ada pilihan "Iklan untuk") |

Daftar dalam `routes/web.php` app sebenar, di belakang auth pemilik:

```php
Route::get('/pelanggan', \App\Livewire\Clients\Index::class);
Route::get('/posting-sedia-ada', \App\Livewire\AdSets\ExistingPosts::class);
```

## Belum termasuk

- Login pelanggan sendiri / Facebook Login (Fasa 7).
- Report kepada pelanggan (Fasa 6).
- Bil yuran urus — buat manual dulu.
