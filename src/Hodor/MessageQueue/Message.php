<?php

namespace Hodor\MessageQueue;

use PhpAmqpLib\Message\AMQPMessage;

class Message
{
    /**
     * @var AMQPMessage $amqp_message
     */
    private $amqp_message;

    /**
     * @var bool
     */
    private $is_loaded;

    /**
     * @var mixed
     */
    private $content;

    /**
     * @param AMQPMessage $amqp_message
     */
    public function __construct(AMQPMessage $amqp_message)
    {
        $this->amqp_message = $amqp_message;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        if ($this->is_loaded) {
            return $this->content;
        }

        $this->content = json_decode($this->amqp_message->body);

        return $this->content;
    }

    public function acknowledge()
    {
        $this->amqp_message->delivery_info['channel']
            ->basic_ack($this->amqp_message->delivery_info['delivery_tag']);
    }
}