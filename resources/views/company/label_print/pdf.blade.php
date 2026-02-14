<style>
    .label {
        width: 200px;
        height: 120px;
        border: 1px solid #000;
        float: left;
        margin: 5px;
        text-align: center;
        padding: 5px;
    }
</style>


@foreach($labels as $label)

<div class="label">

    {!! DNS2D::getBarcodeHTML($label->qr_code, 'QRCODE') !!}

    <br>

    {!! DNS1D::getBarcodeHTML($label->barcode, 'C128') !!}

    <br>

    {{ $label->qr_code }}

</div>

@endforeach