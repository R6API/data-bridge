<?php
declare(strict_types=1);

namespace App\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrawlerCollectCommand extends Command
{
    const ROOT_FILE = 'main';
    const FILES = [
        'rewards',
        'operators',
        'ranks',
        'weapons',
        'seasons'
    ];

    const REGEX = '#(https\:\/\/ubistatic-a\.akamaihd\.net\/0058\/prod\/)?assets\/(data|scripts)\/__FILE__\.[a-zA-Z0-9]{16}\.js(on)?#im';

    /** @var CacheItemPoolInterface */
    protected $cacheApp;

    /** @var string */
    protected $manifestCacheTag;

    public function __construct(CacheItemPoolInterface $cacheApp, string $manifestCacheTag = 'ubisoftManifest')
    {
        $this->cacheApp = $cacheApp;
        $this->manifestCacheTag = $manifestCacheTag;

        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('crawler:collect')
            ->setDescription('Used to collect urls for R6S data files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);

        $matches = [];
        $hasVendorFile = preg_match(
            str_replace('__FILE__', static::ROOT_FILE, static::REGEX),
            file_get_contents('https://game-rainbow6.ubi.com/'),
            $matches
        );

        if ($hasVendorFile) {
            $vendorFile = file_get_contents($this->formatUrl($matches[0]));

            $manifest = [];
            foreach (static::FILES as $file) {
                $matches = [];
                $hasMatched = preg_match(
                    str_replace('__FILE__', $file, static::REGEX),
                    $vendorFile,
                    $matches
                );

                if ($hasMatched) {
                    $manifest[$file] = $this->formatUrl($matches[0]);
                }
            }

            $manifest['updated'] = time();

            // save manifest
            $manifestItem = $this->cacheApp->getItem($this->manifestCacheTag);
            $manifestItem->set(json_encode($manifest));
            $this->cacheApp->save($manifestItem);

            $style->success('Manifest saved.');
            return;
        }

        $style->error('Error when crawling the manifest.');
        return;
    }

    private function formatUrl($url, $baseUrl = 'https://game-rainbow6.ubi.com/')
    {
        if (false === strpos($url, 'https')) {
            $url = $baseUrl.$url;
        }
        return $url;
    }
}