<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockGalleryMedia extends Model
{
    protected $table = 'stock_gallery_media';
    protected $guarded = ['id'];
    protected $casts = ['settings' => 'array', 'approved' => 'boolean', 'enabled' => 'boolean'];

    public function matchesSource(ItemSet $item): bool
    {
        return $this->source_path === $item->image_path
            && $this->source_disk === ($item->image_disk ?: config('filesystems.item_image_disk', 'public'));
    }

    public static function defaults(): array
    {
        return ['scale' => 1, 'x' => 0, 'y' => 0, 'rotation' => 0, 'finger' => 'ring', 'earring_mode' => 'single'];
    }
}
