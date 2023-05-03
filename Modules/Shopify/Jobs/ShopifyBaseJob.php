<?php

namespace Modules\Shopify\Jobs;

use App\Enums\JobStatus;
use App\Jobs\Middleware\ActiveTenant;
use App\Models\SalesChannelRequest;
use App\Services\ShopifyApi;
use App\Traits\JobUpdatePayload;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Shopify\Entities\ShopifyChannel;
use Modules\Shopify\Jobs\SyncJob;
use Modules\Shopify\Repositories\ShopifyChannelRepository;

abstract class ShopifyBaseJob extends SyncJob
{
    use JobUpdatePayload;

    public ShopifyChannel $shopifyChannel;

    public ?int $salesChannelRequestId = null;

    // public ?SalesChannelRequest $salesChannelRequest = null;

    public $nextCursor;

    public string $nextTokenKey;

    public $payload;

    public $releaseJobOnThrottle = true;

    public bool $isSalesChannelRequest = false;

    abstract public function makeRequestPayload();

    abstract public function importData();

    public function __construct($jobBody, $tries = null)
    {
        ini_set('memory_limit', -1);

        parent::__construct($jobBody, $tries);

        $this->jobTags[] = 'Shopify';

        $this->salesChannelRequestId = @$jobBody['sales_channel_request_id'];

        $this->uniqueId = 'T:'.$this->getTenantId().'-S:'.$this->jobBody['sales_channel_id'].'-'.get_class($this);
    }

    public function jobCompleted()
    {
        // $this->updateJobPayload([
        //     'job_status' => JobStatus::COMPLETED(),
        // ]);
    }

    public function setWalmartSalesChannel()
    {
        $this->shopifyChannel = app(ShopifyChannelRepository::class)->getShopifyChannelById($this->jobBody['sales_channel_id']);

        return $this;
    }

    public function makeWalmartRequest()
    {
        $shopifyRequest = $this->jobBody['shopify_request'];
        $response       = (new ShopifyApi($this->shopifyChannel))->request($shopifyRequest['method'], $shopifyRequest['uri'], $shopifyRequest['parameters']);

        if ($response->ok()) {
            $this->payload = $response->json();
        } else {
            if ($response->status() === 520 || $response->status() === 521 || $response->status() === 404) {
                //TODO: HANDLE THIS
                throw new \Exception('Shopify request not OK - Status Code:'.$response->status().' Body '.json_encode($response->json()), 1);

                return;

                if ($this->releaseJobOnThrottle) {
                    $this->release(5);
                }

                return false;
            } else {
                throw new \Exception('Shopify request not OK - Status Code:'.$response->status().' Body '.json_encode($response->json()), 1);
            }
        }

        return true;
    }



    public function getShopifyChannel(): ShopifyChannel
    {
        return $this->shopifyChannel;
    }

    /**
     * @return \App\Models\SalesChannelRequest
     */
    // public function getSalesChannelRequest(): SalesChannelRequest
    // {
    //     if ($this->salesChannelRequestId && ! $this->salesChannelRequest) {
    //         $this->salesChannelRequest = (new SalesChannelRequestRepository())->getSalesChannelRequestById($this->salesChannelRequestId);
    //     }

    //     return $this->salesChannelRequest;
    // }

    public function handle()
    {
        $this->initializeTenant();

        $this->setWalmartSalesChannel();

        // //Set channel request
        // if ($this->isSalesChannelRequest) {
        //     $this->getSalesChannelRequest();
        // }

        $this->makeRequestPayload();

        if ($this->makeWalmartRequest()) {
            $this->importData();

            // if ($this->hasNextPage()) {
            //     $this->updateJobPayload([
            //         'job_status' => JobStatus::RELEASED(),
            //     ]);

            //     $this->release(2);

            //     return;
            // } else {
            //     $this->updateJobPayload([
            //         'job_status' => JobStatus::COMPLETED(),
            //     ]);

            //     $this->salesChannelRequest->completed();
            // }

            $this->jobCompleted();
        } else {
            return;
        }
    }

    public function middleware()
    {
        return [
            (new WithoutOverlapping($this->uniqueId))->expireAfter($this->timeout)->dontRelease(),
            // new ActiveTenant($this->jobBody['tenant_id']),
        ];
    }
}
