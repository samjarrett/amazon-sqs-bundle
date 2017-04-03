<?php


namespace Cotd\AmazonSqsBundle\Model;

use Cotd\AmazonSqsBundle\QueueManager;

class Registry
{
    /**
     * @var QueueManager[]
     */
    private $queues;

    /**
     * Registry constructor.
     * @param QueueManager[] $queues
     */
    public function __construct(array $queues)
    {
        $this->queues = $queues;
    }

    /**
     * @param string $name
     * @return QueueManager
     */
    public function getQueue($name)
    {
        if (array_key_exists($name, $this->queues)) {
            return $this->queues[$name];
        }

        throw new \OutOfBoundsException(sprintf('%s is not in your list of configured queues.', $name));
    }

    /**
     * @return QueueManager[]
     */
    public function getQueues()
    {
        return $this->queues;
    }
}
