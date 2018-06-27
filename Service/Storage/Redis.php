<?php

namespace Noxlogic\RateLimitBundle\Service\Storage;

use Noxlogic\RateLimitBundle\Service\RateLimitInfo;
use Predis\Client;

class Redis implements StorageInterface
{
    /**
     * @var \Predis\Client
     */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getRateInfo($key)
    {
        $info = $this->client->hgetall($key);
        if (!isset($info['limit']) || !isset($info['calls']) || !isset($info['reset'])) {
            return false;
        }

        $rateLimitInfo = new RateLimitInfo();
        $rateLimitInfo->setLimit($info['limit']);
        $rateLimitInfo->setCalls($info['calls']);
        $rateLimitInfo->setResetTimestamp($info['reset']);

        return $rateLimitInfo;
    }

    public function limitRate($key)
    {
        $info = $this->getRateInfo($key);
        if (!$info) {
            return false;
        }

        $this->client->hincrby($key, 'calls', 1);

        return $info;
    }

    public function createRate($key, $limit, $period)
    {
        $reset = time() + $period;

        $this->client->hset($key, 'limit', $limit);
        $this->client->hset($key, 'calls', 1);
        $this->client->hset($key, 'reset', $reset);
        $this->client->expire($key, $period);

        $rateLimitInfo = new RateLimitInfo();
        $rateLimitInfo->setLimit($limit);
        $rateLimitInfo->setCalls(1);
        $rateLimitInfo->setResetTimestamp($reset);

        return $rateLimitInfo;
    }

    public function resetRate($key)
    {
        $this->client->del($key);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setBlock(RateLimitInfo $rateLimitInfo, $periodBlock)
    {
        $resetTimestamp = time() + $periodBlock;
        $this->client->hset($rateLimitInfo->getKey(), 'blocked', 1);
        $this->client->hset($rateLimitInfo->getKey(), 'reset', $resetTimestamp);
        $this->client->expire($rateLimitInfo->getKey(), $periodBlock);

        $rateLimitInfo->setBlocked(true);
        $rateLimitInfo->setResetTimestamp($resetTimestamp);

        return true;
    }
}
