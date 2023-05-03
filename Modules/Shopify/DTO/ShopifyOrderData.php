<?php

namespace Modules\Shopify\DTO\Orders;

use Spatie\LaravelData\Data;

class ShopifyOrderData extends Data
{
    public int $id;
    public string $admin_graphql_api_id;
    public int $app_id;
    public string $browser_ip;
    public bool $buyer_accepts_marketing;
    public string $cancel_reason;
    public string $cancelled_at;
    public string $cart_token;
    public string $checkout_id;
    public string $checkout_token;
    public array $client_details;
    public string $closed_at;
    public bool $confirmed;
    public string $contact_email;
    public string $created_at;
    public string $currency;
    public float $current_subtotal_price;
    public array $current_subtotal_price_set;
    public float $current_total_discounts;
    public array $current_total_discounts_set;
    public string $current_total_duties_set;
    public float $current_total_price;
    public float $current_total_tax;
    public array $current_total_tax_set;
    public string $customer_locale;
    public string $device_id;
    public array $discount_codes;
}
