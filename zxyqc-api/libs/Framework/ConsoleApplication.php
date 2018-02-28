<?php

namespace Framework;


use Framework\Command\CacheClearCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Finder\Finder;

class ConsoleApplication extends Application
{
    /**
     * @var \AppKernel
     */
    private $kernel;

    public function __construct(\AppKernel $kernel)
    {
        parent::__construct('Zxy QC APP', $kernel->getVersion());
        $this->getDefinition()->addOption(
            new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.')
        );

        $this->kernel = $kernel;

        $this->add(new CacheClearCommand());

        $this->registerCommands();
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $container = $this->kernel->getContainer();

        foreach ($this->all() as $command) {
            if ($command instanceof ContainerAwareInterface) {
                $command->setContainer($container);
            }
        }

        $this->setDispatcher($container->get('dispatcher'));

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        if (!is_dir($dir = $this->kernel->getRoot().'/../src/Command')) {
            return;
        }

        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);

        $prefix = 'Command';
        foreach ($finder as $file) {
            $ns = $prefix;
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\'.str_replace('/', '\\', $relativePath);
            }
            $class = $ns.'\\'.$file->getBasename('.php');

            try {
                $r = new \ReflectionClass($class);
                if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract(
                    ) && !$r->getConstructor()->getNumberOfRequiredParameters()
                ) {
                    $this->add($r->newInstance());
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}