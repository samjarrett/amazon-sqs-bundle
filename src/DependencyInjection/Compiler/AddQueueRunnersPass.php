<?php

namespace Cotd\AmazonSqsBundle\DependencyInjection\Compiler;

use Cotd\AmazonSqsBundle\DependencyInjection\CotdAmazonSqsExtension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds all configured payment_method.voter services to the payment method helper.
 *
 * @see Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass
 */
class AddQueueRunnersPass implements CompilerPassInterface
{
    const TAG_NAME = 'amazon_sqs.task_runner';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(CotdAmazonSqsExtension::TASK_RUNNER_REGISTRY_PROTOTYPE_SERVICE_NAME)) {
            return;
        }

        $queues = [];

        foreach ($container->findTaggedServiceIds(self::TAG_NAME) as $id => $tags) {
            foreach ($tags as $tag) {
                $queue = $tag['queue'];
                if (!array_key_exists($queue, $queues)) {
                    $serviceId = sprintf(CotdAmazonSqsExtension::TASK_RUNNER_REGISTRY_PROTOTYPE_SERVICE_NAME, $queue);
                    if (!$container->hasDefinition($serviceId)) {
                        throw new \InvalidArgumentException(sprintf('Unknown sqs queue ("%s") tagged against service %s', $queue, $id));
                    }

                    $queues[$queue] = $container->getDefinition(sprintf(CotdAmazonSqsExtension::TASK_RUNNER_REGISTRY_PROTOTYPE_SERVICE_NAME, $queue));
                }

                if (!array_key_exists('task', $tag)) {
                    throw new \InvalidArgumentException(sprintf('No task name defined against service %s with queue %s', $id, $queue));
                }

                $queues[$queue]->addMethodCall('add', array($tag['task'], new Reference($id)));
            }
        }
    }
}
