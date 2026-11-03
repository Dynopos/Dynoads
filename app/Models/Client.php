<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Peniaga yang iklannya diurus oleh DynoPOS melalui Partner Access.
 */
class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact_name', 'contact_phone',
        'page_id', 'ad_account_id', 'wa_phone',
        'page_name', 'ad_account_name', 'access_checked_at',
        'consented_at', 'consent_note', 'is_active',
    ];

    protected $casts = [
        'access_checked_at' => 'datetime',
        'consented_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    /** "701202512896650" dan "act_701202512896650" kedua-duanya diterima. */
    protected function adAccountId(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => self::normaliseAdAccountId($value),
        );
    }

    /** "018-792 2844" → "60187922844". Meta mahu format antarabangsa, digit sahaja. */
    protected function waPhone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => self::normalisePhone($value),
        );
    }

    public static function normaliseAdAccountId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? '' : 'act_'.$digits;
    }

    public static function normalisePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return str_starts_with($digits, '0') ? '6'.$digits : $digits;
    }

    /**
     * Sebab iklan pelanggan ini belum boleh dibuat, atau null kalau boleh.
     *
     * Persetujuan wajib kerana duit yang dibelanjakan ialah duit pelanggan,
     * dicaj ke kad mereka sendiri (peraturan mutlak #6).
     */
    public function launchBlocker(): ?string
    {
        return match (true) {
            ! $this->is_active => "Pelanggan {$this->name} tidak aktif.",
            $this->consented_at === null => "Belum ada persetujuan bertulis daripada {$this->name}. Rekod persetujuan dulu di skrin Pelanggan.",
            default => null,
        };
    }
}
