<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
@page {
    size: 100mm 190mm;
    margin: 3mm;
}

body {
    margin: 0;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 7.5px;
}

/* TABLE */
.sheet-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.sheet-table td {
    width: 50%;
    padding: 0.8mm;
    vertical-align: top;
}

/* LABEL BOX (🔥 reduced height) */
.label-box {
    border: 1px solid #000;
    height: 16mm; /* 🔥 reduced from 18mm */
    padding: 0.8mm;
    box-sizing: border-box;
}

/* SHIFT */
.left-shift { margin-top: -2mm; }
.right-shift { margin-top: 5mm; }

/* INNER */
.left-col {
    float: left;
    width: 65%;
    line-height: 1.1; /* 🔥 tighter */
}

.right-col {
    float: right;
    width: 33%;
    text-align: right;
    line-height: 1.1;
}

/* TEXT */
.code {
    font-weight: bold;
    font-size: 7.5px;
    margin-top: 0.5mm; /* 🔥 reduced */
}

/* QR */
.qr {
    width: 8mm;
    height: 8mm;
    margin-top: 0; /* 🔥 removed space */
}

/* CLEAR */
.clear { clear: both; }
</style>
</head>

<body>

@php
$total = $itemSets->count();
$half = ceil($total / 2);

$leftItems = $itemSets->slice(0, $half)->values();
$rightItems = $itemSets->slice($half)->values();

$maxRows = max($leftItems->count(), $rightItems->count());
@endphp

<table class="sheet-table">

@for($i = 0; $i < $maxRows; $i++)
<tr>

{{-- LEFT --}}
@php $left = $leftItems->get($i); @endphp
<td>
    <div class="label-box left-shift">

        @if($left)
        <div class="left-col">
            <div>G: {{ number_format($left->gross_weight ?? 0, 3) }}</div>
            <div>L: {{ $left->other ?? '' }}</div>
            <div>N: {{ number_format($left->net_weight ?? 0, 3) }}</div>
            <div>OC: {{ $left->sale_other ?? '' }}</div>
        </div>

        <div class="right-col">
            <div class="code">{{ $left->qr_code }}</div>
            <img class="qr" src="{{ $left->qr_base64 ?? '' }}">
        </div>

        <div class="clear"></div>
        @endif

    </div>
</td>

{{-- RIGHT --}}
@php $right = $rightItems->get($i); @endphp
<td>
    <div class="label-box right-shift">

        @if($right)
        <div class="left-col">
            <div>G: {{ number_format($right->gross_weight ?? 0, 3) }}</div>
            <div>L: {{ $right->other ?? '' }}</div>
            <div>N: {{ number_format($right->net_weight ?? 0, 3) }}</div>
            <div>OC: {{ $right->sale_other ?? '' }}</div>
        </div>

        <div class="right-col">
            <div class="code">{{ $right->qr_code }}</div>
            <img class="qr" src="{{ $right->qr_base64 ?? '' }}">
        </div>

        <div class="clear"></div>
        @endif

    </div>
</td>

</tr>
@endfor

</table>

</body>
</html>