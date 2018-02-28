<?php

namespace Framework\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class CacheClearCommand  extends Command implements ContainerAwareInterface
{

    /** @var  Container */
    protected $container;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDescription('Clears the cache')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command clears the application cache
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $realCacheDir = $this->container->getParameter('cache_dir');

        $filesystem   = new Filesystem();

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        if ($filesystem->exists($realCacheDir)) {
            $filesystem->remove($realCacheDir);
        }

        mkdir($realCacheDir, 0, true);

        $output->writeln('Clearing the cache');

    }

    /**
     * Sets the container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
