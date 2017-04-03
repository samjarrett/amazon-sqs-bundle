<?php


namespace Cotd\AmazonSqsBundle\Model;

class QueueAttributes
{
    /**
     * The approximate number of visible messages in a queue.
     * (For more information, see [Resources Required to Process Messages][1] in the Amazon SQS Developer Guide.)
     *
     * [1]: http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-resources-required-process-messages.html "Resources Required to Process Messages"
     *
     * @var int
     */
    private $approximateNumberOfMessages;

    /**
     * The approximate number of messages that are waiting to be added to the queue.
     *
     * @var int
     */
    private $approximateNumberOfMessagesDelayed;

    /**
     * The approximate number of messages that have not timed-out and aren't deleted.
     * (For more information, see [Resources Required to Process Messages][1] in the Amazon SQS Developer Guide.)
     *
     * [1]: http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-resources-required-process-messages.html "Resources Required to Process Messages"
     *
     * @var int
     */
    private $approximateNumberOfMessagesNotVisible;

    /**
     * The limit of how many bytes a message can contain before Amazon SQS rejects it.
     *
     * @var int
     */
    private $maximumMessageSize;

    /**
     * The number of seconds for which Amazon SQS retains a message.
     *
     * @var int
     */
    private $messageRetentionPeriod;

    /**
     * The Amazon resource name (ARN) of the queue.
     *
     * @var string
     */
    private $arn;

    /**
     * @var array|null
     */
    private $redrivePolicy;

    /**
     * The default delay on the queue in seconds.
     *
     * @var int
     */
    private $defaultDelaySeconds;

    /**
     * The number of seconds for which the ReceiveMessage action waits for a message to arrive.
     *
     * @var string
     */
    private $defaultReceiveMessageWaitTimeSeconds;

    /**
     * The visibility timeout for the queue.
     * (For more information about the visibility timeout, see [Visibility Timeout][2] in the Amazon SQS Developer Guide.)
     *
     * [2]: http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-visibility-timeout.html "Visibility Timeout"
     *
     * @var
     */
    private $defaultVisibilityTimeout;

    /**
     * The date/time when the queue was created.
     *
     * (A friendly version of the `CreatedTimestamp` linux timestamp value)
     *
     * @var \DateTime
     */
    private $createdAt;

    /**
     * The time when the queue was last changed.
     * (Note: Interaction with messages does not count towards this value)
     *
     * (A friendly version of the `LastModifiedTimestamp` linux timestamp value)
     *
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * Whether the queue is FIFO.
     * (For more information, see [FIFO Queue Logic][3] in the Amazon SQS Developer Guide.)
     *
     * [3]: http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/FIFO-queues.html#FIFO-queues-understanding-logic "FIFO Queue Logic"
     *
     * @var bool
     */
    private $fifoQueue;

    /**
     * Whether content-based deduplication is enabled for the queue.
     * (For more information, see [Exactly-Once Processing][4] in the Amazon SQS Developer Guide.)
     *
     * [4]: http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/FIFO-queues.html#FIFO-queues-exactly-once-processing "Exactly-Once Processing"
     *
     * @var bool
     */
    private $contentBasedDeduplication;

    public function __construct(array $attributes)
    {
        $this->arn = $attributes['QueueArn'];
        $this->approximateNumberOfMessages = intval($attributes['ApproximateNumberOfMessages']);
        $this->approximateNumberOfMessagesNotVisible = intval($attributes['ApproximateNumberOfMessagesNotVisible']);
        $this->approximateNumberOfMessagesDelayed = intval($attributes['ApproximateNumberOfMessagesDelayed']);
        $this->createdAt = new \DateTime(sprintf('@%s', $attributes['CreatedTimestamp']));
        $this->updatedAt = new \DateTime(sprintf('@%s', $attributes['LastModifiedTimestamp']));
        $this->defaultVisibilityTimeout = intval($attributes['VisibilityTimeout']);
        $this->maximumMessageSize = intval($attributes['MaximumMessageSize']);
        $this->messageRetentionPeriod = intval($attributes['MessageRetentionPeriod']);
        $this->defaultDelaySeconds = intval($attributes['DelaySeconds']);
        $this->defaultReceiveMessageWaitTimeSeconds = intval($attributes['ReceiveMessageWaitTimeSeconds']);
        $this->fifoQueue = $attributes['FifoQueue'] === 'true';
        $this->contentBasedDeduplication = $attributes['ContentBasedDeduplication'] === 'true';

        if (array_key_exists('RedrivePolicy', $attributes)) {
            $this->redrivePolicy = json_decode($attributes['RedrivePolicy'], true);
        }
    }

    /**
     * @return int
     */
    public function getApproximateNumberOfMessages()
    {
        return $this->approximateNumberOfMessages;
    }

    /**
     * @return int
     */
    public function getApproximateNumberOfMessagesDelayed()
    {
        return $this->approximateNumberOfMessagesDelayed;
    }

    /**
     * @return int
     */
    public function getApproximateNumberOfMessagesNotVisible()
    {
        return $this->approximateNumberOfMessagesNotVisible;
    }

    /**
     * @return int
     */
    public function getMaximumMessageSize()
    {
        return $this->maximumMessageSize;
    }

    /**
     * @return int
     */
    public function getMessageRetentionPeriod()
    {
        return $this->messageRetentionPeriod;
    }

    /**
     * @return string
     */
    public function getArn()
    {
        return $this->arn;
    }

    /**
     * @return array|null
     */
    public function getRedrivePolicy()
    {
        return $this->redrivePolicy;
    }

    /**
     * @return int
     */
    public function getDefaultDelaySeconds()
    {
        return $this->defaultDelaySeconds;
    }

    /**
     * @return string
     */
    public function getDefaultReceiveMessageWaitTimeSeconds()
    {
        return $this->defaultReceiveMessageWaitTimeSeconds;
    }

    /**
     * @return mixed
     */
    public function getDefaultVisibilityTimeout()
    {
        return $this->defaultVisibilityTimeout;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return bool
     */
    public function isFifoQueue()
    {
        return $this->fifoQueue;
    }

    /**
     * @return bool
     */
    public function isContentBasedDeduplication()
    {
        return $this->contentBasedDeduplication;
    }
}
