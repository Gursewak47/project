<?php

namespace Modules\Shopify\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShopifyOrder
{
    use HasFactory;

    protected $fillable = [
        'id',
        'metadata',
    ];

    protected $casts = [
        'id'       => 'integer',
        'metadata' => 'array',
    ];
    protected $hidden = [
        'created_at' => 'dateTime',
        'updated_at' => 'dateTime',
        'deleted_at' => 'dateTime',
    ];
}
