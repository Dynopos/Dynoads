<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANDAIAN FASA 0 — gantikan semasa merge.
 *
 * Migration ini mewakili jadual Fasa 0 yang sudah wujud dalam app live.
 * Ia ditulis di sini semata-mata supaya suite ujian Fasa 1 ada skema untuk
 * dijalankan. Bila kerja ini digabungkan ke dalam repo sebenar, padam fail
 * ini dan kekalkan migration Fasa 0 anda yang asal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('brief_id')->nullable();
            $table->string('name');
            $table->text('pain_point')->nullable();
            $table->text('offer')->nullable();
            $table->json('geo_json')->nullable();
            $table->unsignedInteger('daily_budget_sen')->default(3700);
            $table->string('status')->default('draft');
            $table->string('phase')->default('launch');
            $table->unsignedTinyInteger('round')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_sets');
    }
};
