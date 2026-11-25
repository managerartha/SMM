<?php

// Dipakai NumberingTest: proses terpisah yang meminta nomor secara bersamaan.
// php tests/Support/number_worker.php <jumlah>
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gen = $app->make(App\Domain\Numbering\NumberGenerator::class);
$out = [];
for ($i = 0; $i < (int) ($argv[1] ?? 10); $i++) {
    $out[] = $gen->next('ncr', ['tahun' => '2027']);
}
echo json_encode($out);
