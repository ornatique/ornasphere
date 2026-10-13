<?php
namespace Tests\Feature;

use App\Models\ItemSet;
use App\Services\StockGalleryPdfImage;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockGalleryPdfImageTest extends TestCase
{
    public function test_pdf_images_are_resized_cached_and_refreshed_after_updates(): void
    {
        if (!function_exists('imagecreatetruecolor')) $this->markTestSkipped('GD required');
        Cache::shouldReceive('store')->with('file')->andReturn(new Repository(new ArrayStore));
        Storage::fake('pdf-test');
        $image = imagecreatetruecolor(1600, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 200, 100));
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        Storage::disk('pdf-test')->put('photo.png', $bytes);
        $item = new ItemSet;
        $item->forceFill(['id'=>1,'company_id'=>1,'image_disk'=>'pdf-test','image_path'=>'photo.png','image_mime'=>'image/png','updated_at'=>'2026-10-07 12:00:00']);
        $service = new StockGalleryPdfImage;
        $uri = $service->dataUri($item);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $uri);
        $size = getimagesizefromstring(base64_decode(explode(',', $uri, 2)[1]));
        $this->assertSame(700, $size[0]);
        $this->assertSame(525, $size[1]);
        $this->assertSame($bytes, Storage::disk('pdf-test')->get('photo.png'));
        Storage::disk('pdf-test')->delete('photo.png');
        $this->assertSame($uri, $service->dataUri($item));
        $item->image_path = null;
        $this->assertNull($service->dataUri($item));
        $item->image_path = 'photo.png';
        Storage::disk('pdf-test')->put('photo.png', '');
        $item->updated_at = '2026-10-07 13:00:00';
        $this->assertNull($service->dataUri($item));
    }
}
