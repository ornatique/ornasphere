<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin-top: 0mm;
            margin-right: 8mm;
            margin-bottom: 4mm;
            margin-left: 8mm;
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
            margin-top: -0.4mm;
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
            font-size: 8px;
        }

        .qr {
            width: 7.5mm;
            height: 7.5mm;
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
    @php
    $pages = $printPages ?? $itemSets->values()->chunk(22);
    @endphp

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

            {{-- LEFT COLUMN --}}
            <div class="column">
                @foreach($leftItems as $item)
                <div class="label left-label">
                    @if($item)
                        <div class="left-col">
                            <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                            <div class="code">{{ $item->qr_code }}</div>
                        </div>

                        <div class="right-col">
                            <div class="code">{{ $item->qr_code }}</div>
                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                            <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>

                        </div>
                    @endif

                    <div class="clear"></div>

                </div>
                @endforeach
            </div>


            {{-- RIGHT COLUMN --}}
            <div class="column right-column">
                @foreach($rightItems as $index => $item)
                <div class="label right-label {{ $index === 0 ? 'first' : '' }}">
                    @if($item)
                        <div class="left-col">
                            <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                            <div class="code">{{ $item->qr_code }}</div>
                        </div>

                        <div class="right-col">
                            <div class="code">{{ $item->qr_code }}</div>
                            <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                            <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                        </div>
                    @endif

                    <div class="clear"></div>

                </div>
                @endforeach
            </div>

        </div>
    </div>
    @endforeach
</body>

</html>
