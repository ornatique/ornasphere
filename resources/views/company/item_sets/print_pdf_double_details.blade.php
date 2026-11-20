<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 4mm 8mm 4mm 1mm; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; }
        .page { position: relative; height: 285mm; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        /* Fixed positions preserve the staggered 22-slot label stock in DomPDF. */
        .label { position: absolute; width: 53.9mm; height: 15.5mm; }
        .half { position: absolute; top: 0; width: 26.95mm; height: 15.5mm; }
        .qr { position: absolute; left: 0.4mm; top: 3.5mm; width: 8.5mm; height: 8.5mm; }
        .meta { position: absolute; left: 10.5mm; top: 2.8mm; font-size: 7px;
            font-weight: bold; line-height: 1.02; white-space: nowrap; }
    </style>
</head>
<body>
@foreach(($printPages ?? $itemSets->values()->chunk(22)) as $pageItems)
    <div class="page">
    @foreach($pageItems->values() as $slot => $item)
        @if($item)
        <div class="label" style="left: {{ 50 + ($slot % 2) * 54.9 }}mm; top: {{ intdiv($slot, 2) * 16.1 + ($slot % 2) * 7 }}mm;">
            @foreach([0, 26.95] as $offset)
            <div class="half" style="left: {{ $offset }}mm;">
                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                <div class="meta">
                    <div>{{ $item->qr_code }}</div>
                    <div>W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                    <div>L: {{ number_format($item->other ?? 0, 3) }}</div>
                    <div>N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                    <div>OC: {{ 0 + ($item->sale_other ?? 0) }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    @endforeach
    </div>
@endforeach
</body>
</html>
