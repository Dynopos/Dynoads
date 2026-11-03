<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelanggan yang iklannya diurus oleh DynoPOS.
 *
 * Tiada token di sini, dan memang tak akan ada. Pelanggan beri Partner Access
 * kepada Business Manager DynoPOS, kemudian Page dan ad account mereka di-assign
 * kepada System User yang token-nya sudah ada dalam .env. Satu token, banyak
 * aset. Kad bank pelanggan pula kekal dalam ad account mereka sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('page_id');
            $table->string('ad_account_id');
            $table->string('wa_phone');
            // Diisi oleh "Semak akses" — bukti System User memang nampak aset ni.
            $table->string('page_name')->nullable();
            $table->string('ad_account_name')->nullable();
            $table->timestamp('access_checked_at')->nullable();
            // Persetujuan bertulis pelanggan (contohnya balasan "Setuju" di WhatsApp).
            $table->timestamp('consented_at')->nullable();
            $table->text('consent_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['page_id', 'ad_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
