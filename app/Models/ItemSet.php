<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSet extends Model
{
    public function pricingCompany()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function getBillingSalePriceAttribute()
    {
        return \App\Services\WholesalePrice::price($this);
    }

    public function toArray()
    {
        $data = parent::toArray();
        if (!\App\Services\WholesalePrice::enabled($this->pricingCompany)) unset($data['wholesale_price']);
        $data['billing_sale_price'] = $this->billing_sale_price;
        unset($data['pricing_company']);
        return $data;
    }

    protected $appends = ['billing_sale_price'];

    protected static function booted(): void
    {
        static::saving(function ($item) {
            if ($item->isDirty('wholesale_price')) {
                if (!\App\Services\WholesalePrice::enabled($item->pricingCompany)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['wholesale_price' => 'Wholesale Price is not enabled for this company.']);
                }
                if ($item->wholesale_price !== null && $item->wholesale_price !== '') {
                    if (!is_numeric($item->wholesale_price) || (float) $item->wholesale_price <= 0 || (float) $item->wholesale_price > 9999999999999.99) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['wholesale_price' => 'Enter a positive Wholesale Price.']);
                    }
                    $item->gross_weight ??= 0;
                    $item->net_weight ??= 0;
                } else $item->wholesale_price = null;
            }
            if ($item->isDirty('sale_price') && $item->sale_price !== null && $item->sale_price !== '') {
                \App\Services\FixedSalePrice::amount($item->sale_price);
                $item->gross_weight ??= 0;
                $item->net_weight ??= 0;
            }
        });
    }

    protected $casts = [
        'quantity' => 'integer',
        'available_quantity' => 'integer',
        'printed_at' => 'datetime',
        'image_uploaded_at' => 'datetime',
    ];

    protected $fillable = [
        'sale_price',
        'wholesale_price',

        'company_id',
        'created_by',
        'updated_by',
        'item_id',

        'gross_weight',
        'other',
        'net_weight',
        'quantity',
        'available_quantity',

        'sale_labour_formula',
        'sale_labour_rate',
        'sale_labour_amount',

        'sale_other',

        'supplier_person',
        'size',
        'HUID',
        'is_final',
        'qr_code',
        'barcode',
        'serial_no',
        'is_printed',
        'is_sold',
        'printed_at',
        'image_disk',
        'image_path',
        'image_mime',
        'image_size',
        'image_uploaded_at',


    ];


    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function galleryMedia()
    {
        return $this->hasOne(StockGalleryMedia::class);
    }
}
