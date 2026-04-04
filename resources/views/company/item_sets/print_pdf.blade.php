<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
        }

        .sheet-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sheet-table td {
            width: 50%;
            padding: 0 1.5mm 2.5mm 0;
            vertical-align: top;
        }

        .sheet-table td:nth-child(2n) {
            padding-right: 0;
        }

        .label-box {
            border: 1px solid #444;
            height: 23mm;
            box-sizing: border-box;
            padding: 1.5mm 2mm;
        }

        .label-inner {
            width: 100%;
        }

        .left-col,
        .right-col {
            display: inline-block;
            vertical-align: top;
        }

        .left-col {
            width: 68%;
            line-height: 1.2;
        }

        .right-col {
            width: 30%;
            text-align: right;
        }

        .code {
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .meta-row {
            white-space: nowrap;
        }

        .qr {
            width: 16mm;
            height: 16mm;
            margin-top: 0.5mm;
        }
    </style>
</head>
<body>
    <table class="sheet-table">
        @foreach($itemSets->chunk(2) as $pair)
            <tr>
                @foreach($pair as $set)
                    <td>
                        <div class="label-box">
                            <div class="label-inner">
                                <div class="left-col">
                                    <div class="meta-row">G: {{ number_format((float)$set->gross_weight, 3) }}</div>
                                    <div class="meta-row">O: {{ number_format((float)$set->other, 3) }}</div>
                                    <div class="meta-row">N: {{ number_format((float)$set->net_weight, 3) }}</div>
                                    <div class="meta-row">OC: {{ number_format((float)$set->sale_other, 2) }}</div>
                                    <div class="code">{{ $set->qr_code }}</div>
                                </div>
                                <div class="right-col">
                                    <img class="qr" src="{{ $set->qr_base64 }}" alt="QR">
                                </div>
                            </div>
                        </div>
                    </td>
                @endforeach

                @if($pair->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>
