<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Preview</title>
    <style>
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; background: #f3f4f6; }
        .toolbar {
            position: sticky; top: 0; z-index: 10;
            background: #fff; border-bottom: 1px solid #ddd; padding: 10px 12px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn { border: 0; padding: 8px 12px; border-radius: 4px; cursor: pointer; color: #fff; }
        .btn-print { background: #198754; }
        .btn-update { background: #0d6efd; margin-right: 8px; }
        .toolbar form { display: inline-flex; align-items: center; gap: 8px; }
        .toolbar input[type="number"] { width: 90px; padding: 6px; }
        .sheet-wrap { padding: 16px; }
        .sheet {
            width: 120mm; min-height: 205mm; background: #fff; margin: 0 auto 16px auto;
            box-shadow: 0 1px 5px rgba(0,0,0,.2); box-sizing: border-box;
            padding: 0mm 5mm 4mm 5mm; font-size: 7px;
        }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .wrapper { width: 110mm; margin-left: 0; margin-top: -0.2mm; transform: translateY(-0.2mm); }
        .column { width: 49%; display: inline-block; vertical-align: top; }
        .label { border: 1px solid #000; height: 15.5mm; padding: 0.4mm; box-sizing: border-box; margin-bottom: 0.6mm; overflow: hidden; }
        .label-inner { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .left-col, .right-col {
            width: 50%;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 0.2mm;
        }
        .code { font-weight: bold; line-height: 1.1; font-size: 7px; }
        .qr { width: 7.2mm; height: 7.2mm; display: block; margin: 0 auto; }
        .right-label.first { margin-top: 7mm; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>Preview:</strong> {{ ucfirst(str_replace('_', ' ', $labelFormat)) }} | <strong>Total:</strong> {{ count($ids ?? []) }}</div>
        <div>
            <form method="POST" action="{{ route('company.item_sets.printPreview.post', $company->slug) }}" style="display:inline-flex;">
                @csrf
                <input type="hidden" name="label_format" value="{{ $labelFormat }}">
                <input type="hidden" name="ids_csv" value="{{ implode(',', $ids ?? []) }}">
                @foreach(($ids ?? []) as $id)
                    <input type="hidden" name="ids[]" value="{{ $id }}">
                @endforeach
                <label for="start_position"><strong>Start Position</strong></label>
                <input type="number" id="start_position" name="start_position" min="1" max="22" value="{{ $startPosition ?? 1 }}">
                <button type="submit" class="btn btn-update">Update Preview</button>
            </form>
            <form method="POST" action="{{ route('company.item_sets.printDirect.post', $company->slug) }}" target="_blank" style="display:inline-flex;">
                @csrf
                <input type="hidden" name="label_format" value="{{ $labelFormat }}">
                <input type="hidden" name="start_position" value="{{ $startPosition ?? 1 }}">
                <input type="hidden" name="ids_csv" value="{{ implode(',', $ids ?? []) }}">
                @foreach(($ids ?? []) as $id)
                    <input type="hidden" name="ids[]" value="{{ $id }}">
                @endforeach
                <button type="submit" class="btn btn-print">Direct Print</button>
            </form>
        </div>
    </div>

    <div class="sheet-wrap">
        @php $pages = $printPages ?? $itemSets->values()->chunk(22); @endphp
        @foreach($pages as $pageItems)
            @php
                $leftItems = collect();
                $rightItems = collect();
                for ($i = 0; $i < 11; $i++) {
                    $leftItems->push($pageItems->get($i * 2));
                    $rightItems->push($pageItems->get(($i * 2) + 1));
                }
            @endphp
            <div class="sheet page">
                <div class="wrapper">
                    <div class="column">
                        @foreach($leftItems as $item)
                            <div class="label left-label">
                                @if($item)
                                    <div class="label-inner">
                                    <div class="left-col">
                                        <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                        @if($labelFormat === 'double_barcode')
                                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                            <div class="code">{{ $item->qr_code }}</div>
                                        @else
                                            <div class="code">L: {{ $item->other ?? '' }}</div>
                                            <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                                            <div class="code">OC: {{ $item->sale_other ?? '' }}</div>
                                        @endif
                                    </div>
                                    <div class="right-col">
                                        @if($labelFormat === 'double_barcode')
                                            <div class="code">{{ $item->qr_code }}</div>
                                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                            <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                        @else
                                            <div class="code">{{ $item->qr_code }}</div>
                                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                        @endif
                                    </div>
                                    </div>
                                @endif
                                <div class="clear"></div>
                            </div>
                        @endforeach
                    </div>

                    <div class="column">
                        @foreach($rightItems as $index => $item)
                            <div class="label right-label {{ $index === 0 ? 'first' : '' }}">
                                @if($item)
                                    <div class="label-inner">
                                    <div class="left-col">
                                        <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                        @if($labelFormat === 'double_barcode')
                                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                            <div class="code">{{ $item->qr_code }}</div>
                                        @else
                                            <div class="code">L: {{ $item->other ?? '' }}</div>
                                            <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                                            <div class="code">OC: {{ $item->sale_other ?? '' }}</div>
                                        @endif
                                    </div>
                                    <div class="right-col">
                                        @if($labelFormat === 'double_barcode')
                                            <div class="code">{{ $item->qr_code }}</div>
                                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                            <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                        @else
                                            <div class="code">{{ $item->qr_code }}</div>
                                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                        @endif
                                    </div>
                                    </div>
                                @endif
                                <div class="clear"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
