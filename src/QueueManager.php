<?php


namespace Cotd\AmazonSqsBundle;

use Aws\Credentials\Credentials;
use Aws\Sqs\SqsClient;
use Cotd\AmazonSqsBundle\Exception\TaskRunnerNotFoundException;
use Cotd\AmazonSqsBundle\Model\QueueAttributes;
use Cotd\AmazonSqsBundle\Model\Task;
use Psr\Log\LoggerAwareTrait;

class QueueManager
{
    use LoggerAwareTrait;

    const CREDENTIALS_MODE_PROFILE = 'profile';
    const CREDENTIALS_MODE_KEY = 'key';
    const CREDENTIALS_MODE_NULL = 'null';

    /**
     * 200 KiB - SQS' max message size (256KiB) less some buffer for encoding
     */
    const MESSAGE_LENGTH_BYTES = 200000;
    const MESSAGE_LENGTH_ENTRIES = 10;

    const DEFAULT_WAIT_TIME = 20;
    const DEFAULT_WORKER_TIME = 300;
    const DEFAULT_JOB_COUNT = 1;

    const PRIOR_RECEIVE_ATTEMPT_WARNING = 3;

    /**
     * @var string
     */
    private $queueUrl;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var TaskRunnerRegistry
     */
    private $registry = [];

    /**
     * Manager constructor.
     * @param string $queueUrl
     * @param string $region
     * @param TaskRunnerRegistry $registry
     * @param array|null $credentials
     */
    public function __construct($queueUrl, $region, TaskRunnerRegistry $registry, array $credentials = null)
    {
        $this->queueUrl = $queueUrl;
        $this->registry = $registry;

        $configuration = [
            'region' => $region,
            'version' => '2012-11-05',
        ];
        if (!empty($credentials)) {
            switch ($credentials['mode']) {
                case self::CREDENTIALS_MODE_PROFILE:
                    $configuration['profile'] = $credentials['profile'];
                    break;

                case self::CREDENTIALS_MODE_KEY:
                    $configuration['credentials'] = new Credentials($credentials['access_key_id'], $credentials['secret_key']);
                    break;
            }
        }

        $this->sqsClient = new SqsClient($configuration);
    }

    /**
     * @return SqsClient
     */
    protected function getSqsClient()
    {
        return $this->sqsClient;
    }

    /**
     * @param string $task
     * @param mixed $arguments
     * @param int $delay
     * @return string
     */
    public function enqueueTask($task, $arguments, $delay = 0, $messageGroupId = null, $deduplicationId = null, $alreadyEncoded = false)
    {
        if (!$this->registry->has($task)) {
            throw new \DomainException(sprintf('Unable to enqueue task "%s" as there is no registered runner for it', $task));
        }

        $body = $arguments;
        if (!$alreadyEncoded) {
            $body = json_encode($body);
        }
        $bytes = mb_strlen($body, '8bit');
        if ($bytes > self::MESSAGE_LENGTH_BYTES) {
            throw new \OutOfBoundsException(sprintf('Encoded message was too long: %sKiB provided - %sKiB maximum', $bytes / 1000, self::MESSAGE_LENGTH_BYTES  / 1000));
        }

        $deduplicationId = $deduplicationId ?: hash('sha256', $task . ';' . $body);
        $messageGroupId = $messageGroupId ?: $deduplicationId;

        $result = $this->getSqsClient()->sendMessage([
            'MessageGroupId' => $messageGroupId,
            'MessageDeduplicationId' => $deduplicationId,
            'DelaySeconds' => $delay,
            'MessageAttributes' => [
                'task' => [
                    'DataType' => 'String',
                    'StringValue' => $task,
                ],
            ],
            'MessageBody' => $body,
            'QueueUrl' => $this->queueUrl,
        ]);

        return $result->get('MessageId');
    }

    public function batchEnqueueTasks(array $tasks, $alreadyEncoded = false)
    {
        $ids = [];
        $entries = [];
        $totalBytes = 0;

        foreach ($tasks as $task) {
            $body = $task['arguments'];
            if (!$alreadyEncoded) {
                $body = json_encode($body);
            }

            $id = hash('sha256', $task['task'] . ';' . $body);
            $deduplicationId = array_key_exists('deduplicationId', $task) ? $task['deduplicationId'] : $id;
            $messageGroupId = array_key_exists('messageGroupId', $task) ? $task['messageGroupId'] : $id;
            $delay = array_key_exists('delay', $task) ? $task['delay'] : 0;

            $entry = [
                'Id' => $id,
                'MessageGroupId' => $messageGroupId,
                'MessageDeduplicationId' => $deduplicationId,
                'DelaySeconds' => $delay,
                'MessageAttributes' => [
                    'task' => [
                        'DataType' => 'String',
                        'StringValue' => $task['task'],
                    ],
                ],
                'MessageBody' => $body,
            ];

            $bytes = mb_strlen(json_encode($entry), '8bit');

            if ($totalBytes + $bytes > self::MESSAGE_LENGTH_BYTES || count($entries) == self::MESSAGE_LENGTH_ENTRIES) {
                $result = $this->sendMessageBatch($entries, [
                    'stackBytes' => $totalBytes,
                    'currentBytes' => $bytes,
                    'totalBytes' => $totalBytes + $bytes,
                    'byteLimit' => self::MESSAGE_LENGTH_BYTES,
                    'count' => count($entries),
                ]);

                if ($result->hasKey('Successful')) {
                    $ids = array_merge($ids, array_column($result->get('Successful'), 'MessageId'));
                }

                $entries = [];
                $totalBytes = 0;
            }

            $entries[] = $entry;
            $totalBytes += $bytes;
        }

        if (count($entries) > 0) {
            $result = $this->sendMessageBatch($entries, [
                'stackBytes' => $totalBytes,
                'currentBytes' => $bytes ?: 0,
                'totalBytes' => $totalBytes + $bytes,
                'byteLimit' => self::MESSAGE_LENGTH_BYTES,
                'count' => count($entries),
            ]);

            if ($result->hasKey('Successful')) {
                $ids = array_merge($ids, array_column($result->get('Successful'), 'MessageId'));
            }
        }

        return [
            'successful' => count($ids),
            'failed' => count($tasks) - count($ids),
            'ids' => $ids,
        ];
    }

    private function sendMessageBatch($entries, $debugData = [], $isRetry = false)
    {
        if (count($entries) == 0) {
            $this->logger->critical('Attempted to call SQS SendMessageBatch with no messages (suggests a single message was greater than the maximum size)', $debugData);

            throw new \DomainException('Attempted to call SQS SendMessageBatch with no messages (suggests a single message was greater than the maximum size)');
        }

        try {
            $result = $this->getSqsClient()->sendMessageBatch([
                'Entries' => $entries,
                'QueueUrl' => $this->queueUrl,
            ]);
        } catch (\Exception $e) {
            $logDebugData = [];
            $retryMessage = ' This will be reattempted.';

            if ($isRetry) {
                $logDebugData = $debugData;
                $retryMessage = ''; // second failed attempt is not retried
                $logDebugData['entries'] = json_encode($entries);
            }

            $logDebugData['exceptionMessage'] = $e->getMessage();
            $this->logger->error(sprintf('SQS SendMessageBatch call failure: {exceptionMessage}.%s', $retryMessage), $logDebugData);

            if (!$isRetry) {
                return $this->sendMessageBatch($entries, $debugData, true);
            }

            throw $e;
        }

        return $result;
    }

    /**
     * @param int $waitTime
     * @param int $workerTime
     * @param int $count
     * @return int
     */
    public function getTask($waitTime = self::DEFAULT_WAIT_TIME, $workerTime = self::DEFAULT_WORKER_TIME, $count = self::DEFAULT_JOB_COUNT)
    {
        $this->logger->debug('Fetching {count} tasks from queue {queueUrl} (timeout: {waitTime})', [
            'count' => $count,
            'queueUrl' => $this->queueUrl,
            'waitTime' => $waitTime,
        ]);

        $startTime = microtime(true);
        $result = $this->getSqsClient()->receiveMessage([
            'AttributeNames' => ['All'],
            'MessageAttributeNames' => ['All'],
            'QueueUrl' => $this->queueUrl,
            'WaitTimeSeconds' => $waitTime,
            'VisibilityTimeout' => $workerTime,
            'MaxNumberOfMessages' => $count,
        ]);

        $messages = $result->hasKey('Messages') ? $result->get('Messages') : [];

        $this->logger->debug('Retrieved {count} messages after {time} waiting', [
            'count' => count($messages),
            'time' => number_format(microtime(true) - $startTime, 3),
        ]);

        foreach ($messages as $message) {
            $body = json_decode($message['Body'], true);
            $task = new Task(
                $message['MessageId'],
                $message['ReceiptHandle'],
                $body,
                $message['Body'],
                $message['Attributes'],
                $this->parseMessageAttributes($message['MessageAttributes'])
            );

            $this->runTask($task);
        }

        return count($messages);
    }

    /**
     * @param Task $task
     */
    private function runTask(Task $task)
    {
        $type = $task->getMessageAttribute('task');
        $id = $task->getId();
        $priorReceiveAttempts = $task->getAttribute('ApproximateReceiveCount');

        $this->logger->info('Fetched {type} task with ID={id}', [
            'type' => $type,
            'id' => $id,
        ]);
        try {
            $taskRunner = $this->registry->get($type);
            $this->logger->debug('Processing task {type}/{id} with runner {runner}', [
                'type' => $type,
                'id' => $id,
                'runner' => get_class($taskRunner),
            ]);
            $result = $taskRunner->executeTask($task);

            $this->logger->info('Successfully processed {type} task with ID={id}', [
                'type' => $type,
                'id' => $id,
            ]);
            if ($result === true || $task->isCompleted()) {
                $this->completeTask($task);
                $task->complete();
                $this->logger->info('Auto-completed task with ID={id}', [
                    'id' => $id,
                ]);
            }

            if (!$task->isCompleted() && $priorReceiveAttempts >= self::PRIOR_RECEIVE_ATTEMPT_WARNING) {
                $this->logger->notice('Task {type}/{id} completed processing (attempt #{priorReceiveAttempts}) but was not marked as complete', [
                    'type' => $type,
                    'id' => $id,
                    'priorReceiveAttempts' => $priorReceiveAttempts,
                ]);
            }
        } catch (TaskRunnerNotFoundException $e) {
            $this->logger->critical('Unable to process task with type={type}: No associated runner is registered', [
                'type' => $type,
            ]);
        }
    }

    /**
     * @param array $messageAttributes
     * @return array
     */
    private function parseMessageAttributes(array $messageAttributes)
    {
        $parsedAttributes = [];
        foreach ($messageAttributes as $name => $attribute) {
            $valueAttribute = $attribute['DataType'] . 'Value';
            $parsedAttributes[$name] = $attribute[$valueAttribute];
        }

        return $parsedAttributes;
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function completeTask(Task $task)
    {
        $this->getSqsClient()->deleteMessage([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $task->getReceiptHandle(),
        ]);

        return true;
    }

    /**
     * Retrieve attributes of the queue
     *
     * @return QueueAttributes
     */
    public function getAttributes()
    {
        $result = $this->getSqsClient()->getQueueAttributes([
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => [ 'All' ],
        ]);

        return new QueueAttributes($result->get('Attributes'));
    }
}
