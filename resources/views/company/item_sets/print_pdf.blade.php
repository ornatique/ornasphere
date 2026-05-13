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


/* MAIN 2 COLUMN LAYOUT */
.wrapper {
    width: 110mm;
    margin-left: 50mm;
    margin-top: -0.4mm;
    transform: translateY(-0.9mm);
}

/* LEFT + RIGHT COLUMN */
.column {
    width: 49%;
    display: inline-block;
    vertical-align: top;
}

/* LABEL */
.label {
    border: 0px solid #000;
    height: 15.5mm;
    padding: 0.4mm;
    box-sizing: border-box;
    margin-bottom: 0.6mm;
    page-break-inside: avoid;
}

/* 🔥 SHIFT CONTROL */
.left-label {
    margin-top: 0;
}

.right-label.first {
    margin-top: 7mm;
}

.right-label.other {
    margin-top: 0;
}

/* CONTENT */
.left-col {
    float: left;
    width: 49%;
}

.right-col {
    float: right;
    width: 49%;
    position: relative;
    text-align: left;
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

.clear {
    clear: both;
}

.page {
    page-break-after: always;
}

.page:last-child {
    page-break-after: auto;
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
                        <div class="code">L: {{ $item->other ?? '' }}</div>
                        <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                        <div class="code">OC: {{ $item->sale_other ?? '' }}</div>
                    </div>

                    <div class="right-col">
                        <div class="code">{{ $item->qr_code }}</div>
                        <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                    </div>
                @endif

                <div class="clear"></div>

            </div>
        @endforeach
    </div>


    {{-- RIGHT COLUMN --}}
    <div class="column">
        @foreach($rightItems as $index => $item)

            @php
                $class = ($index == 0) ? 'first' : 'other';
            @endphp

            <div class="label right-label {{ $class }}">
                @if($item)
                    <div class="left-col">
                        <div class="code">W: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                        <div class="code">L: {{ $item->other ?? '' }}</div>
                        <div class="code">N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                        <div class="code">OC: {{ $item->sale_other ?? '' }}</div>
                    </div>

                    <div class="right-col">
                        <div class="code">{{ $item->qr_code }}</div>
                        <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
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
