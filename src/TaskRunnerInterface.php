<?php


namespace Cotd\AmazonSqsBundle;

use Cotd\AmazonSqsBundle\Model\Task;

interface TaskRunnerInterface
{
    public function executeTask(Task $task);
}
