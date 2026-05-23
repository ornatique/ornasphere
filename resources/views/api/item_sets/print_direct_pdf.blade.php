<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Direct Print</title>
    <style>
        @page {
            size: A4 portrait;
            margin-top: 4mm;
            margin-right: 8mm;
            margin-bottom: -6mm;
            margin-left: 1mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 6.5px;
        }

        .page {
            page-break-after: always;
        }

        .wrapper {
            width: 110mm;
            margin-left: 50mm;
        }

        .column {
            width: 49%;
            display: inline-block;
            vertical-align: top;
        }

        .label {
            border: 0 solid #000;
            height: 15.5mm;
            padding: 0.4mm;
            box-sizing: border-box;
            margin-bottom: 0.6mm;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .label-inner {
            display: table;
            table-layout: fixed;
            width: 100%;
            height: 100%;
        }

        .left-col,
        .right-col {
            width: 50%;
            height: 100%;
            display: table-cell;
            text-align: center;
            vertical-align: middle;
        }

        .code {
            font-weight: bold;
            line-height: 1.1;
            font-size: 7px;
        }

        .qr {
            width: 8.5mm;
            height: 8.5mm;
            display: block;
            margin: 0 auto;
        }

        .label.double-details .code {
            font-size: 7px;
            line-height: 1.02;
        }

        .label.double-details .qr {
            width: 8.5mm;
            height: 8.5mm;
            margin: 0;
        }

        .dd-half {
            width: 49%;
            height: 100%;
            display: table-cell;
            text-align: center;
            vertical-align: middle;
        }

        .dd-meta {
            display: inline-block;
            vertical-align: middle;
            text-align: left;
            margin-left: 2mm;
        }

        .dd-meta .code {
            white-space: nowrap;
        }

        .right-label.first {
            margin-top: 7mm;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .clear {
            clear: both;
        }
    </style>

</head>

<body>
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
        <div class="page">
            <div class="wrapper">
                <div class="column">
                    @foreach($leftItems as $item)
                    <div class="label left-label {{ $labelFormat === 'double_details' ? 'double-details' : '' }}">
                        @if($item)
                        <div class="label-inner">
                            @if($labelFormat === 'double_details')
                            <div class="dd-half">
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                <div class="dd-meta">

                                    <div class="code">{{ $item->qr_code }}</div>
                                    <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                    <div class="code">L: {{ number_format($item->other ?? 0, 3) }}</div>
                                    <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                                    <div class="code">OC: {{ number_format($item->sale_other ?? 0, 2) }}</div>
                                </div>
                            </div>
                            <div class="dd-half">
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                <div class="dd-meta">

                                    <div class="code">{{ $item->qr_code }}</div>
                                    <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                    <div class="code">L: {{ number_format($item->other ?? 0, 3) }}</div>
                                    <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                                    <div class="code">OC: {{ number_format($item->sale_other ?? 0, 2) }}</div>
                                </div>
                            </div>
                            @else
                            <div class="left-col">
                                @if($labelFormat === 'double_barcode')
                                <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                <div class="code">{{ $item->qr_code }}</div>
                                @else
                                <div class="code">{{ $item->qr_code }}</div>
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
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
                            @endif
                        </div>
                        @endif
                        <div class="clear"></div>
                    </div>
                    @endforeach
                </div>
                <div class="column">
                    @foreach($rightItems as $index => $item)
                    <div class="label right-label {{ $index === 0 ? 'first' : '' }} {{ $labelFormat === 'double_details' ? 'double-details' : '' }}">
                        @if($item)
                        <div class="label-inner">
                            @if($labelFormat === 'double_details')
                            <div class="dd-half">
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                <div class="dd-meta">

                                    <div class="code">{{ $item->qr_code }}</div>
                                    <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                    <div class="code">L: {{ number_format($item->other ?? 0, 3) }}</div>
                                    <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                                    <div class="code">OC: {{ number_format($item->sale_other ?? 0, 2) }}</div>
                                </div>
                            </div>
                            <div class="dd-half">
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                <div class="dd-meta">

                                    <div class="code">{{ $item->qr_code }}</div>
                                    <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                    <div class="code">L: {{ number_format($item->other ?? 0, 3) }}</div>
                                    <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                                    <div class="code">OC: {{ number_format($item->sale_other ?? 0, 2) }}</div>
                                </div>
                            </div>
                            @else
                            <div class="left-col">
                                @if($labelFormat === 'double_barcode')
                                <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                                <div class="code">{{ $item->qr_code }}</div>
                                @else
                                <div class="code">{{ $item->qr_code }}</div>
                                <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
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
                            @endif
                        </div>
                        @endif
                        <div class="clear"></div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
        <script>
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
</body>

</html>
