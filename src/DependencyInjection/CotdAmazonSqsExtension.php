<?php

namespace Cotd\AmazonSqsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class CotdAmazonSqsExtension extends Extension
{
    const TASK_RUNNER_REGISTRY_PROTOTYPE_SERVICE_NAME = 'cotd_amazon_sqs.task_runner_registry_prototype';
    const TASK_RUNNER_REGISTRY_SERVICE_PREFIX = 'cotd_amazon_sqs.task_runner_registry.%s';

    const QUEUE_MANAGER_PROTOTYPE_SERVICE_NAME = 'cotd_amazon_sqs.queue_manager_prototype';
    const QUEUE_MANAGER_SERVICE_PREFIX = 'cotd_amazon_sqs.queue_manager.%s';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        foreach ($config['queues'] as $name => $configuration) {
            $registryDefinition = new DefinitionDecorator(self::TASK_RUNNER_REGISTRY_PROTOTYPE_SERVICE_NAME);
            $container->setDefinition(sprintf(self::TASK_RUNNER_REGISTRY_SERVICE_PREFIX, $name), $registryDefinition);

            $managerDefinition = new DefinitionDecorator(self::QUEUE_MANAGER_PROTOTYPE_SERVICE_NAME);
            $managerDefinition->replaceArgument(0, $configuration['queue_url']);
            $managerDefinition->replaceArgument(1, $configuration['region']);
            $managerDefinition->replaceArgument(2, new Reference(sprintf(self::TASK_RUNNER_REGISTRY_SERVICE_PREFIX, $name)));
            $managerDefinition->replaceArgument(3, $configuration['credentials']);
            $container->setDefinition(sprintf(self::QUEUE_MANAGER_SERVICE_PREFIX, $name), $managerDefinition);
        }
    }
}
