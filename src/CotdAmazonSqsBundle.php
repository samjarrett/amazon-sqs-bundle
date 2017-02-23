<?php

namespace Cotd\AmazonSqsBundle;

use Cotd\AmazonSqsBundle\DependencyInjection\Compiler\AddQueueRunnersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CotdAmazonSqsBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AddQueueRunnersPass);
    }
}
