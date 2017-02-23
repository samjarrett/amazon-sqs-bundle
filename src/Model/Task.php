<?php


namespace Cotd\AmazonSqsBundle\Model;

class Task
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $receiptHandle;

    /**
     * @var array
     */
    private $data;

    /**
     * @var string
     */
    private $rawData;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @var array
     */
    private $messageAttributes;

    /**
     * @var bool
     */
    private $completed = false;

    public function __construct($id, $receiptHandle, array $data, $rawData, array $attributes, array $messageAttributes)
    {
        $this->id = $id;
        $this->receiptHandle = $receiptHandle;
        $this->data = $data;
        $this->rawData = $rawData;
        $this->attributes = $attributes;
        $this->messageAttributes = $messageAttributes;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getReceiptHandle()
    {
        return $this->receiptHandle;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getRawData(): string
    {
        return $this->rawData;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
    }

    /**
     * @return array
     */
    public function getMessageAttributes()
    {
        return $this->messageAttributes;
    }

    public function getMessageAttribute($name)
    {
        if (array_key_exists($name, $this->messageAttributes)) {
            return $this->messageAttributes[$name];
        }
    }

    public function complete()
    {
        $this->completed = true;
    }

    /**
     * @return bool
     */
    public function isCompleted()
    {
        return $this->completed;
    }
}
