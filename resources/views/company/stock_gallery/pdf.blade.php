<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Gallery</title>
    <style>
        @page { margin: 18px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #171717;
            font-size: 11px;
            margin: 0;
            background: #fff;
        }
        .header {
            border: 1px solid #202020;
            padding: 0;
            margin-bottom: 18px;
            text-align: center;
            background: #fff;
        }
        .company-name {
            font-size: 22px;
            font-weight: 700;
            padding: 11px 10px 4px 10px;
            letter-spacing: 0;
        }
        .title {
            font-size: 16px;
            font-weight: 700;
            padding: 4px 10px;
            background: #1f2133;
            color: #ffffff;
            border-top: 1px solid #202020;
            border-bottom: 1px solid #202020;
        }
        .meta {
            padding: 7px 10px 9px 10px;
            font-size: 11px;
            color: #333;
        }
        .meta span {
            display: inline-block;
            margin: 0 9px;
        }
        .gallery-table {
            width: 100%;
            border-collapse: collapse;
        }
        .gallery-cell {
            width: 50%;
            vertical-align: top;
            padding: 8px 7px 14px 7px;
        }
        .card {
            width: 100%;
            border: 1px solid #d7d7d7;
            background: #ffffff;
            padding: 7px;
        }
        .item-name {
            height: 26px;
            line-height: 24px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            overflow: hidden;
            padding: 0 8px;
            margin-bottom: 7px;
            color: #111827;
            border-bottom: 2px solid #c8a64b;
        }
        .image-box {
            height: 268px;
            text-align: center;
            background: #f7f7f7;
            overflow: hidden;
            width: 100%;
            line-height: 268px;
            border: 1px solid #eeeeee;
        }
        .image-box img {
            max-width: 96%;
            max-height: 252px;
            vertical-align: middle;
        }
        .no-image {
            color: #777;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company->company_name ?? $company->name ?? 'Company' }}</div>
        <div class="title">Stock Gallery</div>
        <div class="meta">
            <span>Generated: {{ now()->format('d-m-Y h:i A') }}</span>
            <span>Total Items: {{ $itemSets->count() }}</span>
        </div>
    </div>

    <div>
        @if($itemSets->isEmpty())
            <p>{{ $emptyMessage ?? 'No stock gallery items found.' }}</p>
        @else
            <table class="gallery-table">
                @foreach($itemSets->chunk(2) as $rowItems)
                    <tr>
                        @foreach($rowItems as $itemSet)
                            @php
                                $payload = $itemSet->stock_gallery_payload;
                                $image = $itemSet->stock_gallery_image;
                            @endphp
                            <td class="gallery-cell">
                                <div class="card">
                                    <div class="item-name">{{ $payload['item_name'] }}</div>
                                    <div class="image-box">
                                        @if($image)
                                            <img src="{{ $image }}" alt="{{ $payload['label_code'] }}">
                                        @else
                                            <span class="no-image">No Image</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        @endforeach

                        @if($rowItems->count() === 1)
                            <td class="gallery-cell"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</body>
</html>
