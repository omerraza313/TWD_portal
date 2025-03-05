<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'description', 
        'price', 
        'stock', 
        'wordpress_permalink', 
        'regular_price', 
        'sale_price', 
        'date_on_sale_from',
        'date_on_sale_to',
        'on_sale',
        'purchasable',
        'total_sales',
        'price_sync',
        'shopify_product_id',
        'is_processed'
    ];

                        
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class)
            ->withPivot('value', 'order')
            ->withTimestamps();
    }
    public function singleAttribute($name)
    {
        return $this->attributes()->Where('slug', $name)->first();
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('order', 'asc');
    }

    public function image()
    {
        return $this->hasOne(ProductImage::class)->where('order', 0);
    }


}
