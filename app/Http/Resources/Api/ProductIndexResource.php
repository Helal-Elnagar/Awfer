<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductIndexResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->getMedia('product-images')->first()?->original_url,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'parent_category_id' => $this->parent_category_id,
            'parent_category_name' => $this->parentCategory?->name,
            'quantity' => $this->quantity,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand?->name,
            'brand_image' => $this->brand?->image ?? null,
            'is_favourite' => $this->is_favourite,
            'in_cart' => $this->in_cart,
            'cart_quantity' => $this->cart_quantity,
            'base_price' => money($this->base_price),
            // 'discount_percentage' => $this->discount_percentage,
            'final_price' => money($this->base_price - ($this->base_price * ($this->discount_percentage / 100))),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
