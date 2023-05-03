
<?php

namespace Modules\Shopify\Jobs;

use App\Models\SalesChannel;
use App\Repositories\SalesChannelRepository;
use Modules\Shopify\Jobs\BaseJob;

abstract class SyncJob extends BaseJob
{
    // public SalesChannel $salesChannel;

    public $jobTags = [];

    public function __construct($jobBody)
    {
        parent::__construct($jobBody);
    }

    public function tags()
    {
        return $this->jobTags;
    }

    /**
     * Get the value of channel
     */
    public function getSalesChannel()
    {
        // return $this->salesChannel;
    }

    /**
     * Set the value of channel
     *
     * @return  self
     */
    public function setSalesChannel($salesChannel = null)
    {
    //     if (! $salesChannel) {
    //         $salesChannel = $this->jobBody['sales_channel_id'];
    //     }

    //     $this->salesChannel = (new SalesChannelRepository())->getSalesChannelById($salesChannel);

        return $this;
    }

    public function syncSuccessful()
    {
        $this->jobCompleted();
    }
}
