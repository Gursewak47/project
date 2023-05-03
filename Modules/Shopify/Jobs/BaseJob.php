<?php

namespace Modules\Shopify\Jobs;

use App\Enums\JobStatus;
use App\Traits\JobUpdatePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    use JobUpdatePayload;

    public array $jobBody;

    protected $tags;

    public $uniqueFor;

    public $uniqueId;

    public $timeout;

    public $backoff = 1;

    public $maxExceptions = 1;

    private string $tenantId;

    public function __construct($jobBody)
    {
        ini_set('memory_limit', '10240M');

        $this->setJobBody($jobBody);

        $this->setTimeout(5 * 60); //5 minutes

        $this->setTags();
    }

    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;

        $this->uniqueFor = $this->timeout;
    }

    /**
     * Get the value of tags
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set the value of tags
     *
     * @return  self
     */
    public function setTags()
    {
        $this->tags = [
            get_class($this),
            'Tenant:'.$this->tenantId,
        ];

        return $this;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds($this->timeout);
    }

    /**
     * This will put the job back in queue so that it can retried.
     *
     * @return void
     */
    public function requeue()
    {
        $this->release();
    }

    /**
     * Get the value of jobBody
     */
    public function getJobBody()
    {
        return $this->jobBody;
    }

    /**
     * Set the value of jobBody
     *
     * @return  self
     */
    public function setJobBody($jobBody)
    {
        $this->jobBody  = $jobBody;
        $this->tenantId = $this->jobBody['tenant_id'];

        return $this;
    }

    /**
     * Load tenant for tenant based jobs;
     *
     * @param int $tenantId
     * @return bool
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById
     */
    public function initializeTenant(string $tenantId = null): bool
    {
        if (! $tenantId) {
            $tenantId = $this->getTenantId();
        }

        //Load Tenant
        app(TenantRepository::class)->initializeTenant($tenantId);

        $this->loadJobObjectIfExist();

        return true;
    }

    /**
     * Load jobBody stored in database.
     *
     * @return void
     */
    public function loadJobObjectIfExist(): void
    {
    }

    public function jobStarted()
    {
    }

    public function jobCompleted()
    {
        $this->updateJobPayload([
            'job_status' => JobStatus::COMPLETED()
        ]);
    }

    /**
     * Get the value of tenantId
     */
    public function getTenantId()
    {
        return $this->getJobBody()['tenant_id'];
    }

    /**
     * Set the value of tenantId
     *
     * @return  self
     */
    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function print($message)
    {
        echo "$message"."\n";
    }

    /**
     * Set the value of jobObject
     *
     * @return  self
     */
    public function setJobObject($status = null)
    {
        // if (env('QUEUE_CONNECTION') != 'sync') {
        //     Job::updateOrCreate([
        //         'id' => $this->job->getJobId(),
        //     ], [
        //         'id'       => $this->job->getJobId(),
        //         'metadata' => $this->jobBody,
        //         'status'   => is_null($status) ? Job::STATUS_IN_QUEUE : $status,
        //     ]);
        // }
    }
}
