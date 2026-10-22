<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = ['worker_id'=>1,'worker_name'=>'Demo Worker','metal'=>'Silver','item'=>'Chain','fine_credit'=>0,'credit'=>0];
$entries = [array_merge($base, ['date'=>'2026-10-02','voucher'=>'OS-JI-001','type'=>'Issue','debit'=>500,'fine_debit'=>467.5]),
    array_merge($base, ['date'=>'2026-10-05','voucher'=>'OS-JI-001 / Receive','type'=>'Receive','debit'=>0,'credit'=>490,'fine_debit'=>0,'fine_credit'=>458.15])];
$ledger = ['filters'=>['from_date'=>'2026-10-01','to_date'=>'2026-10-07'],'groups'=>(new App\Services\WorkerMetalLedger)->balances($entries,'2026-10-01')];
$company = (object)['name'=>'Demo Company'];
if (!is_dir(__DIR__.'/../output/pdf')) mkdir(__DIR__.'/../output/pdf',0777,true);
Barryvdh\DomPDF\Facade\Pdf::loadView('company.reports.pdf.worker_metal_ledger',compact('company','ledger'))->setPaper('a4','portrait')->save(__DIR__.'/../output/pdf/worker-metal-ledger-demo.pdf');
