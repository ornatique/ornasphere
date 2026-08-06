<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Labels - {{ $data->voucher_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            color: #000;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        .sheet {
            width: 194mm;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .voucher-meta {
            width: 100%;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #000;
            font-size: 11px;
            font-weight: 700;
        }

        .voucher-meta td {
            width: 33.333%;
            padding: 0;
        }

        .voucher-meta td:nth-child(2) {
            text-align: center;
        }

        .voucher-meta td:nth-child(3) {
            text-align: right;
        }

        .label-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .label-table td {
            width: 33.333%;
            height: 12.85mm;
            padding: 0;
            border: 1px solid #202020;
            vertical-align: middle;
            font-size: 22px;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }

        .label-line {
            display: table;
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }

        .serial {
            display: table-cell;
            width: 38px;
            height: 100%;
            border-right: 1px solid #202020;
            box-shadow: inset 0 -1px 0 #202020;
            background-color: #c8d0cc !important;
            color: #000 !important;
            text-align: center;
            vertical-align: middle;
            font-size: 22px;
            font-weight: 800;
        }

        .label-value {
            display: table-cell;
            padding: 0 8px;
            background-color: #6f7f7c !important;
            color: #fff !important;
            text-align: center;
            vertical-align: middle;
            font-size: 22px;
            font-weight: 800;
            box-shadow: inset 0 -1px 0 #202020;
        }

        .buch-no {
            display: inline-block;
            min-width: 0;
        }

        .buch-weight {
            display: inline-block;
            min-width: 0;
        }

        .empty-label {
            color: #fff;
        }
    </style>
</head>
<body>
    @foreach($labels->chunk(60) as $pageLabels)
        @php
            $pageLabels = $pageLabels->values();
        @endphp
        <section class="sheet">
            <table class="voucher-meta">
                <tr>
                    <td>Voucher No: {{ $data->voucher_no }}</td>
                    <td>Date: {{ optional($data->voucher_date)->format('d-m-Y') }}</td>
                    <td>Company: {{ $company->name }}</td>
                </tr>
            </table>

            <table class="label-table">
                <tbody>
                    @for($row = 0; $row < 20; $row++)
                        @php
                            $left = $pageLabels->get($row);
                            $middle = $pageLabels->get($row + 20);
                            $right = $pageLabels->get($row + 40);
                        @endphp
                        <tr>
                            <td>
                                @if($left)
                                    <div class="label-line">
                                        <span class="serial">{{ $left['serial'] }}</span>
                                        <span class="label-value">
                                            <span class="buch-no">{{ $left['buch_no'] }}</span>
                                            <span class="buch-weight">-{{ $left['size'] }}</span>
                                        </span>
                                    </div>
                                @else
                                    <span class="empty-label">.</span>
                                @endif
                            </td>
                            <td>
                                @if($middle)
                                    <div class="label-line">
                                        <span class="serial">{{ $middle['serial'] }}</span>
                                        <span class="label-value">
                                            <span class="buch-no">{{ $middle['buch_no'] }}</span>
                                            <span class="buch-weight">-{{ $middle['size'] }}</span>
                                        </span>
                                    </div>
                                @else
                                    <span class="empty-label">.</span>
                                @endif
                            </td>
                            <td>
                                @if($right)
                                    <div class="label-line">
                                        <span class="serial">{{ $right['serial'] }}</span>
                                        <span class="label-value">
                                            <span class="buch-no">{{ $right['buch_no'] }}</span>
                                            <span class="buch-weight">-{{ $right['size'] }}</span>
                                        </span>
                                    </div>
                                @else
                                    <span class="empty-label">.</span>
                                @endif
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
