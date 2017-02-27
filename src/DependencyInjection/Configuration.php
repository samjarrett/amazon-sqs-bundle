<?php

namespace Cotd\AmazonSqsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('cotd_amazon_sqs');

        $rootNode
            ->children()
                ->arrayNode('queues')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('queue_url')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('region')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('credentials')
                                ->beforeNormalization()
                                    ->ifTrue(function ($v) {
                                        return is_array($v) && !array_key_exists('mode', $v) && !empty($v);
                                    })
                                    ->then(function ($v) {
                                        if (array_key_exists('profile', $v) && !empty($v['profile'])) {
                                            $v['mode'] = 'profile';
                                        } elseif (array_key_exists('access_key_id', $v) && !empty($v['access_key_id']) && array_key_exists('secret_key', $v) && !empty($v['secret_key'])) {
                                            $v['mode'] = 'key';
                                        }

                                        return $v;
                                    })
                                ->end()
                                ->children()
                                    ->scalarNode('mode')->defaultValue('null')->cannotBeEmpty()->end()
                                    ->scalarNode('profile')->defaultNull()->end()
                                    ->scalarNode('access_key_id')->defaultNull()->end()
                                    ->scalarNode('secret_key')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
