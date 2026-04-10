<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
@page {
    size: 100mm 200mm;
    margin: 3mm;
}

body {
    margin: 0;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 7.5px;
}

/* MAIN 2 COLUMN LAYOUT */
.wrapper {
    width: 100%;
}

/* LEFT + RIGHT COLUMN */
.column {
    width: 49%;
    display: inline-block;
    vertical-align: top;
}

/* LABEL */
.label {
    border: 1px solid #000;
    height: 12mm;
    padding: 1mm;
    box-sizing: border-box;
    margin-bottom: 2mm;
}

/* 🔥 SHIFT CONTROL */
.left-label {
    margin-top: 0.5mm; /* LEFT fixed */
}

.right-label.first {
    margin-top: 9mm; /* FIRST BIG GAP */
}

.right-label.other {
    margin-top: 2.5mm; /* HALF GAP */
}

/* CONTENT */
.left-col {
    float: left;
    width: 65%;
    line-height: 1.1;
}

.right-col {
    float: right;
    width: 33%;
    text-align: right;
}

.code {
    font-weight: bold;
    font-size: 7px;
}

.qr {
    width: 8mm;
    height: 8mm;
}

.clear {
    clear: both;
}
</style>
</head>

<body>

@php
$total = $itemSets->count();
$half = ceil($total / 2);

$leftItems = $itemSets->slice(0, $half)->values();
$rightItems = $itemSets->slice($half)->values();
@endphp

<div class="wrapper">

    {{-- LEFT COLUMN --}}
    <div class="column">
        @foreach($leftItems as $item)
            <div class="label left-label">

                <div class="left-col">
                    <div>G: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                    <div>L: {{ $item->other ?? '' }}</div>
                    <div>N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                    <div>OC: {{ $item->sale_other ?? '' }}</div>
                </div>

                <div class="right-col">
                    <div class="code">{{ $item->qr_code }}</div>
                    <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                </div>

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

                <div class="left-col">
                    <div>G: {{ number_format($item->gross_weight ?? 0, 3) }}</div>
                    <div>L: {{ $item->other ?? '' }}</div>
                    <div>N: {{ number_format($item->net_weight ?? 0, 3) }}</div>
                    <div>OC: {{ $item->sale_other ?? '' }}</div>
                </div>

                <div class="right-col">
                    <div class="code">{{ $item->qr_code }}</div>
                    <img class="qr" src="{{ $item->qr_base64 ?? '' }}">
                </div>

                <div class="clear"></div>

            </div>

        @endforeach
    </div>

</div>

</body>
</html>