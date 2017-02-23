<?php


namespace Cotd\AmazonSqsBundle;

use Cotd\AmazonSqsBundle\Exception\TaskRunnerNotFoundException;

class TaskRunnerRegistry
{
    /**
     * @var array
     */
    private $taskRunners = [];

    /**
     * @param string $type
     * @param TaskRunnerInterface $taskRunner
     */
    public function add($type, TaskRunnerInterface $taskRunner)
    {
        if ($this->has($type)) {
            throw new \DomainException(sprintf('Duplicate task runner registered for task type "%s"', $type));
        }

        $this->taskRunners[$type] = $taskRunner;
    }

    /**
     * @param string $type
     * @return TaskRunnerInterface
     */
    public function get($type)
    {
        if (!$this->has($type)) {
            throw new TaskRunnerNotFoundException(sprintf('Unable to process task - no task runner is registered for task type %s', $type));
        }

        return $this->taskRunners[$type];
    }

    /**
     * @param string $type
     * @return bool
     */
    public function has($type)
    {
        return array_key_exists($type, $this->taskRunners);
    }
}
