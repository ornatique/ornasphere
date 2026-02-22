<!DOCTYPE html>
<html>

<head>
    <style>
        .label {
            width: 200px;
            height: 80px;
            border: 1px solid #000;
            padding: 5px;
            margin: 5px;
            float: left;
        }

        .qr {
            float: right;
        }
    </style>
</head>

<body>

    @foreach($itemSets as $set)

    <div class="label">

        <b>{{ $set->qr_code }}</b><br>

        W: {{ number_format($set->gross_weight,3) }}

        <div class="qr">
            <img src="{{ $set->qr_base64 }}" width="60">
        </div>

    </div>

    @endforeach

</body>

</html>