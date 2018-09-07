<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class Controller extends BaseController
{
    const FILES = [
        'rewards',
        'operators',
        'ranks',
        'weapons',
        'seasons'
    ];

    /** @var KernelInterface */
    protected $kernel;

    /** @var CacheItemPoolInterface */
    protected $cacheApp;

    /** @var string */
    protected $cachePrefix;

    public function __construct(KernelInterface $kernel, CacheItemPoolInterface $cacheApp, string $cachePrefix = 'ubisoftData')
    {
        $this->kernel = $kernel;
        $this->cacheApp = $cacheApp;
        $this->cachePrefix = $cachePrefix;
    }

    public function assets(string $data)
    {
        if (in_array($data, static::FILES)) {
            $dataItem = $this->cacheApp->getItem($this->cachePrefix.ucfirst($data));

            if (null === $dataItem->get()) {
                $application = new Application($this->kernel);
                $application->setAutoExit(false);

                $input = new ArrayInput([
                    'command' => 'crawler:fetch',
                ]);
                $output = new BufferedOutput();

                $application->run($input, $output);
                // $content = $output->fetch();

                $dataItem = $this->cacheApp->getItem($this->cachePrefix.ucfirst($data));
            }
            return new Response($dataItem->get());
        }

        return new JsonResponse();
    }
}