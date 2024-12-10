<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Notifications\NotifyQuantityAlert;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{

    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    protected $with = ['media'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useFallbackUrl('/images/fallback_product_image.png');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50);
    }

    public function setProductCostAttribute($value)
    {
        $this->attributes['product_cost'] = ($value * 100);
    }

    public function getProductCostAttribute($value)
    {
        return ($value / 100);
    }

    public function setProductPriceAttribute($value)
    {
        $this->attributes['product_price'] = ($value * 100);
    }

    public function getProductPriceAttribute($value)
    {
        return ($value / 100);
    }

    public function getAdjustedPriceAttribute()
    {
        $price = $this->product_price;

        if ($this->product_quantity < 20) {
            $price += $price * 0.10; // Tambah 10%
        } elseif ($this->product_quantity < 50) {
            $price += $price * 0.05; // Tambah 5%
        } elseif ($this->product_quantity < 100) {
            $price += $price * 0.02; // Tambah 2%
        }

        return $price;
    }
}
