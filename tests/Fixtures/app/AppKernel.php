<?php

declare(strict_types=1);

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        $bundles = [
            new ApiPlatformBundle(),
            new DoctrineBundle(),
            new FrameworkBundle(),
        ];

        return $bundles;
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        $c->setParameter('kernel.project_dir', __DIR__);

        $loader->load(__DIR__.'/config/config.yaml');

        $c->prependExtensionConfig('framework', [
            'property_access' => ['enabled' => true],
            'secret' => 'symfony.vrok',
            'validation' => ['enable_annotations' => true],
            'serializer' => ['enable_annotations' => true],
            'test' => null,
            'session' => class_exists(SessionFactory::class) ? ['storage_factory_id' => 'session.storage.factory.mock_file'] : ['storage_id' => 'session.storage.mock_file'],
            'profiler' => [
                'enabled' => true,
                'collect' => false,
            ],
            'router' => ['utf8' => true],
            'http_method_override' => false,
        ]);
    }
}
