<?php

namespace Modules\Shopify\Jobs\Orders;

use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Modules\Shopify\Jobs\ShopifyBaseJob;

class RefreshShopifyOrdersJob extends ShopifyBaseJob implements ShouldBeUnique
{
    public function makeRequestPayload()
    {
    }
    public function importData()
    {
    }
}
