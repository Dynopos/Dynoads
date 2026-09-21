<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASA 1 — dari mana creative sesuatu variant datang.
 *
 * Dua lajur ini yang membolehkan laporan membandingkan poster yang kita buat
 * dengan posting yang peniaga sudah ada. Dalam beberapa bulan, perbandingan
 * itu yang akan menjawab soalan sebenar: berbaloikah bayar untuk poster baru?
 *
 * Lalai 'poster' supaya setiap baris sedia ada kekal betul tanpa backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->string('source_type', 20)->default('poster')->after('ad_set_id');
            // ID posting Page, bukan ID creative — format "{page_id}_{post_id}"
            // disusun semasa panggilan Meta, bukan disimpan bercantum.
            $table->string('source_post_id')->nullable()->after('source_type');

            $table->index('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->dropIndex(['source_type']);
            $table->dropColumn(['source_type', 'source_post_id']);
        });
    }
};
