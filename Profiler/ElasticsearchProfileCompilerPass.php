<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Elasticsearch\Client;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @package core
 */
class ElasticsearchProfileCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $isDebugEnabled = $container->getParameterBag()->resolveValue($container->getParameter('kernel.debug'));

        if (!$isDebugEnabled) {
            $container->removeDefinition(DataCollector::class);

            return;
        }

        $clientDecorator = new Definition(ClientProfiler::class);
        $clientDecorator->setArguments([
            new Reference('shopware.es.profiled.client.inner'),
        ]);
        $clientDecorator->setDecoratedService(Client::class);

        $container->setDefinition('shopware.es.profiled.client', $clientDecorator);
    }
}
