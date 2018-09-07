<?php
declare(strict_types=1);

namespace App\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrawlerFetchCommand extends Command
{
    /** @var CacheItemPoolInterface */
    protected $cacheApp;

    /** @var string */
    protected $manifestCacheTag;

    /** @var string */
    protected $cachePrefix;

    public function __construct(CacheItemPoolInterface $cacheApp, string $manifestCacheTag = 'ubisoftManifest', string $cachePrefix = 'ubisoftData')
    {
        $this->cacheApp = $cacheApp;
        $this->manifestCacheTag = $manifestCacheTag;
        $this->cachePrefix = $cachePrefix;

        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('crawler:fetch')
            ->setDescription('Used to fetch files from R6S website to store them locally.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);

        // get manifest from filesystem cache
        $manifestItem = $this->cacheApp->getItem($this->manifestCacheTag);
        if (null === $manifestItem->get()) {
            $style->writeln('No manifest data, let\'s collect them !');
            $this->runCollectCommand($style, $output);

            $manifestItem = $this->cacheApp->getItem($this->manifestCacheTag);
        }
        $manifestItem = json_decode($manifestItem->get(), true);

        // if updated since more than a month, let's force update it !
        if ((time() - (60*60*24*30)) >= $manifestItem['updated']) {
            $style->writeln('Manifest data older than a month, let\'s refresh that.');
            $this->runCollectCommand($style, $output);

            $manifestItem = $this->cacheApp->getItem($this->manifestCacheTag);
            $manifestItem = json_decode($manifestItem->get(), true);
        }

        foreach ($manifestItem as $key => $url) {
            // continue key is ignored since it only stores last time the manifest was updated
            if ('updated' === $key) {
                continue;
            }

            $fetched = false;

            while (!$fetched) {
                try {
                    $style->writeln('Fetching `' .$key. '` file ...');
                    $file = file_get_contents($url);

                    $fileCache = $this->cacheApp->getItem($this->cachePrefix.ucfirst($key));
                    $fileCache->set($file);
                    $fileCache->expiresAfter(30*24*60*60);
                    $this->cacheApp->save($fileCache);
                    $fetched = true;
                } catch (\Exception $e) {
                    $style->writeln('Manifest file: `' .$key. '` not found. Let\'s refresh that.');
                    $this->runCollectCommand($style, $output);

                    $manifestItem = $this->cacheApp->getItem($this->manifestCacheTag);
                    $manifestItem = json_decode($manifestItem->get(), true);
                    $url = $manifestItem[$key];
                }
            }
        }

        $style->success('Files saved.');
        return;
    }

    /**
     * @param SymfonyStyle $style
     * @param OutputInterface $output
     *
     * @throws \Exception
     */
    private function runCollectCommand(SymfonyStyle $style, OutputInterface $output)
    {
        $style->title('Running sub-command: `crawler:collect`');

        $command = $this->getApplication()->find('crawler:collect');

        $tempInput = new ArrayInput([]);
        $returnCode = $command->run($tempInput, $output);


        $style->writeln('Ended sub-command: `crawler:collect`');
    }
}