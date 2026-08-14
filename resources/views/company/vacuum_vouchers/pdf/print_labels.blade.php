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
            font-size: 9pt;
        }

        .sheet {
            width: 550pt;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .voucher-meta {
            width: 100%;
            margin-bottom: 8pt;
            padding-bottom: 5pt;
            border-bottom: 1px solid #000;
            font-size: 8pt;
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
            table-layout: auto;
        }

        .label-table tr {
            height: 36pt;
        }

        .label-table td {
            height: 36pt;
            padding: 0;
            border: 1px solid #202020;
            vertical-align: middle;
            font-size: 16.5pt;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
            overflow: hidden;
        }

        .serial-cell {
            width: 28.5pt;
            min-width: 28.5pt;
            max-width: 28.5pt;
            background-color: #c8d0cc !important;
            color: #000 !important;
            text-align: center !important;
            font-size: 16.5pt;
            font-weight: 800;
        }

        .value-cell {
            width: 154.8pt;
            min-width: 154.8pt;
            max-width: 154.8pt;
            padding: 0 6pt;
            background-color: #6f7f7c !important;
            color: #fff !important;
            text-align: center !important;
            font-size: 16.5pt;
            font-weight: 800;
        }

        .buch-no {
            display: inline-block;
            min-width: 0;
        }

        .buch-weight {
            display: inline-block;
            min-width: 0;
        }

        .empty-label-cell {
            width: 183.3pt;
            background: #fff;
            color: transparent;
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
                <colgroup>
                    <col style="width: 28.5pt;">
                    <col style="width: 154.8pt;">
                    <col style="width: 28.5pt;">
                    <col style="width: 154.8pt;">
                    <col style="width: 28.5pt;">
                    <col style="width: 154.8pt;">
                </colgroup>
                <tbody>
                    @for($row = 0; $row < 20; $row++)
                        @php
                            $left = $pageLabels->get($row);
                            $middle = $pageLabels->get($row + 20);
                            $right = $pageLabels->get($row + 40);
                        @endphp
                        <tr>
                            @if($left)
                                <td class="serial-cell" width="28.5">{{ $left['serial'] }}</td>
                                <td class="value-cell" width="154.8">
                                    <span class="buch-no">{{ $left['buch_no'] }}</span>
                                    <span class="buch-weight">-{{ $left['size'] }}</span>
                                </td>
                            @else
                                <td class="empty-label-cell" colspan="2" width="183.3">.</td>
                            @endif

                            @if($middle)
                                <td class="serial-cell" width="28.5">{{ $middle['serial'] }}</td>
                                <td class="value-cell" width="154.8">
                                    <span class="buch-no">{{ $middle['buch_no'] }}</span>
                                    <span class="buch-weight">-{{ $middle['size'] }}</span>
                                </td>
                            @else
                                <td class="empty-label-cell" colspan="2" width="183.3">.</td>
                            @endif

                            @if($right)
                                <td class="serial-cell" width="28.5">{{ $right['serial'] }}</td>
                                <td class="value-cell" width="154.8">
                                    <span class="buch-no">{{ $right['buch_no'] }}</span>
                                    <span class="buch-weight">-{{ $right['size'] }}</span>
                                </td>
                            @else
                                <td class="empty-label-cell" colspan="2" width="183.3">.</td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
