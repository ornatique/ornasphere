<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Direct Print</title>
    <style>
        @page {
            size: A4 portrait;
            margin-top: 6mm;
            margin-right: 8mm;
            margin-bottom: -6mm;
            margin-left: 7.5mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 6.5px;
        }


        /* @page negative margin is ignored by most PDF engines. */
        .page {
            page-break-after: always;
        }

        .wrapper {
            width: 110mm;
            margin-left: 50mm;
            /* margin-top: -0.4mm; */
            /* transform: translateY(-0.8mm); */
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
        }

        .left-col {
            float: left;
            width: 49%;
            /* line-height: 1.1; */
        }

        .right-col {
            float: right;
            width: 49%;
            /* line-height: 1.1; */
            position: relative;
            /* top: 4px; */
        }

        .code {
            font-weight: bold;
            line-height: 1.1;
            font-size: 7px;
        }

        .qr {
            width: 6.5mm;
            height: 6.5mm;
            display: block;
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
        <script>
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
</body>

</html>