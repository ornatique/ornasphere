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

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #202238;
            color: #fff;
        }

        .toolbar-title {
            font-weight: 700;
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            border: 0;
            padding: 8px 14px;
            cursor: pointer;
            font-size: 14px;
            color: #fff;
            background: #0d6efd;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .sheet {
            width: 194mm;
            min-height: 281mm;
            margin: 0 auto;
            padding: 4mm 0;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .voucher-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #000;
            font-size: 11px;
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
            color: transparent;
        }

        @media print {
            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                width: auto;
                min-height: auto;
                padding: 0;
            }

            .voucher-meta {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="toolbar-title">Print Buch Labels</div>
            <div>{{ $data->voucher_no }} | {{ $labels->count() }} labels</div>
        </div>
        <div class="toolbar-actions">
            <button type="button" class="btn" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-secondary" onclick="window.close()">Close</button>
        </div>
    </div>

    @foreach($labels->chunk(84) as $pageLabels)
        @php
            $pageLabels = $pageLabels->values();
        @endphp
        <section class="sheet">
            <div class="voucher-meta">
                <div><strong>Voucher No:</strong> {{ $data->voucher_no }}</div>
                <div><strong>Date:</strong> {{ optional($data->voucher_date)->format('d-m-Y') }}</div>
                <div><strong>Company:</strong> {{ $company->name }}</div>
            </div>

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

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
