<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class AppKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        $bundles = [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new DoctrineFixturesBundle(),
            new MonologBundle(),
            new TwigBundle(),
            new ApiPlatformBundle(),
            new Vrok\SymfonyAddons\VrokSymfonyAddonsBundle(),
        ];

        return $bundles;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('kernel.project_dir', __DIR__);

        $loader->load(__DIR__.'/config/config.yaml');

        $profiler = [
            'enabled' => true,
            'collect' => false,
        ];

        // Symfony 7.4 deprecates not setting this to true, 8.0 only allows true (the default) and
        // 8.1 deprecates setting it at all.
        // @todo remove when Symfony 7.4 support is dropped
        if (Kernel::VERSION_ID < 80000) {
            $profiler['collect_serializer_data'] = true;
        }

        $container->prependExtensionConfig('framework', [
            'property_access'      => ['enabled' => true],
            'secret'               => 'symfony.vrok',
            'validation'           => [],
            'serializer'           => [],
            'test'                 => null,
            'profiler'             => $profiler,
            'router'               => ['utf8' => true],
            'http_method_override' => false,
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.yaml');
    }
}
