<?php

namespace Hodor\JobQueue;

use DateTime;
use Hodor\MessageQueue\Queue;

class WorkerQueue
{
    /**
     * @var Queue
     */
    private $message_queue;

    /**
     * @var QueueManager
     */
    private $queue_manager;

    /**
     * @param Queue $message_queue
     * @param QueueManager $queue_manager
     */
    public function __construct(Queue $message_queue, QueueManager $queue_manager)
    {
        $this->message_queue = $message_queue;
        $this->queue_manager = $queue_manager;
    }

    /**
     * @param string $name the name of the job to run
     * @param array $params the parameters to pass to the job
     * @param array $meta meta-information about the job
     */
    public function push($name, array $params = [], array $meta = [])
    {
        $this->message_queue->push([
            'name'   => $name,
            'params' => $params,
            'meta'   => $meta,
        ]);
    }

    /**
     * @param  callable $job_runner
     */
    public function runNext(callable $job_runner)
    {
        $this->message_queue->consume(function ($message) use ($job_runner) {
            $start_time = new DateTime;

            register_shutdown_function(function ($message, $start_time, $queue_manager) {
                if (error_get_last()) {
                    $queue_manager->getSuperqueue()->markJobAsFailed(
                        $message,
                        $start_time
                    );
                    exit(1);
                }
            }, $message, $start_time, $this->queue_manager);

            $content = $message->getContent();
            $name = $content['name'];
            $params = $content['params'];
            call_user_func($job_runner, $name, $params);

            $superqueue = $this->queue_manager->getSuperqueue();
            $superqueue->markJobAsSuccessful($message, $start_time);

            exit(0);
        });
    }
}
