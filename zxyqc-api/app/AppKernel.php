<?php

use Framework\Controller\AppControllerResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterListenersPass;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Yaml\Parser;

class AppKernel
{
    const KERNEL_VERSION = '1.0';

    /**
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var Container
     */
    protected $container;

    private $debug;

    public function __construct(Request $request = null, $isDebug = false)
    {
        $this->debug = $isDebug;
        $this->request = $request;
        $this->createContainer();
    }

    public function handle()
    {
        $framework = new HttpKernel($this->getDispatcher(), new AppControllerResolver($this->container));
        $response = $framework->handle($this->request);
        $response->send();
        $framework->terminate($this->request, $response);
    }

    protected function createContainer()
    {
        $file = __DIR__ . '/cache/container.php';

        if (!$this->debug && file_exists($file)) {
            require_once $file;
            $this->container = new MyCachedContainer();
        } else {
            $this->container = new ContainerBuilder(new ParameterBag());
            $this->container->addCompilerPass(new RegisterListenersPass('dispatcher'));

            $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__ . '/config'));
            $loader->load('config.yml');

            $this->container->setParameter('_routes', $this->getRouteDefinitions());
            $this->container->setParameter('cache_dir', $this->getCacheDir());

            $this->container->compile();

            if (!$this->debug) {
                $dumper = new PhpDumper($this->container);
                file_put_contents(
                    $file,
                    $dumper->dump(array('class' => 'MyCachedContainer'))
                );
            }
        }
    }

    private function getRouteDefinitions()
    {
        $fileIterator = new DirectoryIterator(__DIR__ . '/config/routes');
        $_routes = array();
        $yamal = new Parser();

        foreach ($fileIterator as $file) {
            if ($file->isFile()) {
                $routes = $yamal->parse(file_get_contents(__DIR__ . '/config/routes/' . $file->getFilename()));
                $_routes = array_merge($_routes, $routes);
            }
        }

        return $_routes;
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected function getDispatcher()
    {
        return $this->container->get('dispatcher');
    }

    public function getRoot()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return __DIR__ . '/cache' ;
    }

    public function getVersion()
    {
        return self::KERNEL_VERSION;
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return $this->debug;
    }

    public function getContainer()
    {
        return $this->container;
    }
}
