<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class Controller extends BaseController
{
    const FILES = [
        'rewards',
        'operators',
        'ranks',
        'weapons',
        'seasons'
    ];

    /** @var CacheItemPoolInterface */
    protected $cacheApp;

    /** @var string */
    protected $cachePrefix;

    public function __construct(CacheItemPoolInterface $cacheApp, string $cachePrefix = 'ubisoftData')
    {
        $this->cacheApp = $cacheApp;
        $this->cachePrefix = $cachePrefix;
    }

    public function assets(string $data)
    {
        if (in_array($data, static::FILES)) {
            $dataItem = $this->cacheApp->getItem($this->cachePrefix.ucfirst($data));
            return new Response($dataItem->get());
        }

        return new JsonResponse();
    }
}