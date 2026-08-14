<?php

namespace App\Models;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Slider extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = "sliders";
    protected $fillable = ['title', 'link', 'description', 'status'];
    protected $casts = [
        'id'          => 'integer',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'integer',
        'link'        => 'string',
    ];

    public function getImageAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('slider'))) {
            $slider = $this->getMedia('slider')->last();
            if ($slider) {
                try {
                    $coverPath = $slider->getPath('cover');
                    if ($coverPath && file_exists($coverPath)) {
                        return $slider->getUrl('cover');
                    }
                } catch (\Exception $e) {
                    // Fallback to original
                }
                
                try {
                    $originalPath = $slider->getPath();
                    if ($originalPath && file_exists($originalPath)) {
                        return $slider->getUrl();
                    }
                } catch (\Exception $e) {
                    // Fallback to getUrl
                }

                return $slider->getUrl();
            }
        }
        return asset('images/default/slider.png');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('cover')->fit(Fit::Fill, 1689, 600)->keepOriginalImageFormat()->sharpen(10);
    }
}
