<?php

use App\Services\PagePostService;

/**
 * Peraturan 13 dikuatkuasakan sebagai ujian, bukan sekadar komen.
 *
 * Posting peniaga ialah hasil kerja mereka sendiri — kadangkala bertahun
 * terkumpul dengan like dan komen sebenar. Satu panggilan DELETE yang tersilap
 * memusnahkan sesuatu yang tidak boleh dipulihkan, dan tiada permintaan ciri
 * yang berbaloi dengan risiko itu. Jadi ujian ini membaca kod sumber dan
 * gagal kalau sesiapa menambah laluan tulis ke dalam kelas ini.
 */
function pagePostServiceSource(): string
{
    return file_get_contents((new ReflectionClass(PagePostService::class))->getFileName());
}

it('tidak mempunyai panggilan HTTP yang menulis', function () {
    $source = pagePostServiceSource();

    foreach (['Http::post', 'Http::put', 'Http::patch', 'Http::delete', '->post(', '->put(', '->patch(', '->delete('] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }
});

it('tidak mendedahkan method yang namanya menandakan penulisan', function () {
    $methods = collect((new ReflectionClass(PagePostService::class))->getMethods())
        ->map(fn (ReflectionMethod $m) => $m->getName());

    $writeVerbs = ['create', 'update', 'delete', 'publish', 'edit', 'remove', 'destroy', 'store', 'save'];

    foreach ($methods as $method) {
        foreach ($writeVerbs as $verb) {
            expect(str_starts_with(strtolower($method), $verb))->toBeFalse(
                "PagePostService::{$method}() nampak seperti operasi tulis. Peraturan 13: baca sahaja."
            );
        }
    }
});

it('menggunakan kaedah HTTP GET sahaja', function () {
    expect(pagePostServiceSource())->toContain('Http::asJson()')
        ->and(pagePostServiceSource())->toContain('->get(');
});
