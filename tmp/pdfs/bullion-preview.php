<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$company=(object)['name'=>'Sample Jewellery']; $selected=(object)['name'=>'Sample Customer'];
$balance=['cash'=>95000,'gold'=>0,'silver'=>0.231];
$entry=new App\Models\CustomerAdvanceLedger(['entry_date'=>'2026-10-15','entry_type'=>'convert_to_metal','cash_in'=>0,'cash_out'=>5000,'metal_type'=>'silver','metal_in'=>0.231,'metal_out'=>0,'rate'=>21631.63,'remarks'=>'Bullion conversion; rate manually confirmed']);
$entry->running=$balance; $entries=collect([$entry]);
Barryvdh\DomPDF\Facade\Pdf::loadView('company.bullion.pdf',compact('company','selected','entries','balance'))->setPaper('a4','landscape')->save(__DIR__.'/bullion-preview.pdf');
