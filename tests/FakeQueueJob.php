<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Contracts\Queue\Job;

class FakeQueueJob implements Job
{
    public bool $released = false;

    public int $releaseDelay = 0;

    public function uuid()
    {
        return 'fake-job-uuid';
    }

    public function getJobId()
    {
        return 'fake-job-id';
    }

    public function payload()
    {
        return [];
    }

    public function fire()
    {
    }

    public function release($delay = 0)
    {
        $this->released = true;
        $this->releaseDelay = (int) $delay;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function delete()
    {
    }

    public function isDeleted()
    {
        return false;
    }

    public function isDeletedOrReleased()
    {
        return $this->released;
    }

    public function attempts()
    {
        return 1;
    }

    public function hasFailed()
    {
        return false;
    }

    public function markAsFailed()
    {
    }

    public function fail($e = null)
    {
    }

    public function maxTries()
    {
        return null;
    }

    public function maxExceptions()
    {
        return null;
    }

    public function timeout()
    {
        return null;
    }

    public function retryUntil()
    {
        return null;
    }

    public function getName()
    {
        return \Paymob\Laravel\Jobs\ProcessPaymobPayment::class;
    }

    public function resolveName()
    {
        return \Paymob\Laravel\Jobs\ProcessPaymobPayment::class;
    }

    public function resolveQueuedJobClass()
    {
        return \Paymob\Laravel\Jobs\ProcessPaymobPayment::class;
    }

    public function getConnectionName()
    {
        return 'sync';
    }

    public function getQueue()
    {
        return 'default';
    }

    public function getRawBody()
    {
        return '{}';
    }
}
