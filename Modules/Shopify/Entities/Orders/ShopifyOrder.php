<?php

namespace Modules\Shopify\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shopify\DTO\Orders\ShopifyOrderData;

class ShopifyOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'metadata',
    ];

    protected $casts = [
        'id'       => 'integer',
        'metadata' => ShopifyOrderData::class,
    ];
    protected $hidden = [
        'created_at' => 'dateTime',
        'updated_at' => 'dateTime',
        'deleted_at' => 'dateTime',
    ];
}
