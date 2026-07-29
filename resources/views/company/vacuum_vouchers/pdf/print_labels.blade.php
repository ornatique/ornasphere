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
            height: 9.15mm;
            padding: 0 3mm;
            border: 1px solid #000;
            vertical-align: middle;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }

        .serial {
            display: inline-block;
            min-width: 28px;
        }

        .empty-label {
            color: #fff;
        }
    </style>
</head>
<body>
    @foreach($labels->chunk(84) as $pageLabels)
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
                    @for($row = 0; $row < 28; $row++)
                        @php
                            $left = $pageLabels->get($row);
                            $middle = $pageLabels->get($row + 28);
                            $right = $pageLabels->get($row + 56);
                        @endphp
                        <tr>
                            <td>
                                @if($left)
                                    <span class="serial">{{ $left['serial'] }}.</span>
                                    {{ $left['buch_no'] }} - {{ $left['size'] }}
                                @else
                                    <span class="empty-label">.</span>
                                @endif
                            </td>
                            <td>
                                @if($middle)
                                    <span class="serial">{{ $middle['serial'] }}.</span>
                                    {{ $middle['buch_no'] }} - {{ $middle['size'] }}
                                @else
                                    <span class="empty-label">.</span>
                                @endif
                            </td>
                            <td>
                                @if($right)
                                    <span class="serial">{{ $right['serial'] }}.</span>
                                    {{ $right['buch_no'] }} - {{ $right['size'] }}
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
