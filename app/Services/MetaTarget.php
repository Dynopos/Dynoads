<?php

namespace App\Services;

use App\Models\Client;

/**
 * Aset Meta mana yang sesuatu panggilan sasarkan: ad account, Page dan nombor
 * WhatsApp. Token tidak termasuk — semua pelanggan dikendalikan oleh satu
 * System User token dari .env melalui Partner Access.
 */
final class MetaTarget
{
    public function __construct(
        public readonly ?string $adAccountId,
        public readonly ?string $pageId,
        public readonly ?string $waPhone,
        public readonly ?int $clientId = null,
    ) {}

    /** Akaun DynoPOS sendiri — nilai dari .env. */
    public static function owner(): self
    {
        return new self(
            adAccountId: config('dynoads.ad_account_id'),
            pageId: config('dynoads.page_id'),
            waPhone: config('dynoads.wa_phone'),
        );
    }

    public static function forClient(?Client $client): self
    {
        if ($client === null) {
            return self::owner();
        }

        return new self(
            adAccountId: $client->ad_account_id,
            pageId: $client->page_id,
            waPhone: $client->wa_phone,
            clientId: $client->id,
        );
    }
}
