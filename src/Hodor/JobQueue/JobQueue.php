<?php

namespace Hodor\JobQueue;

use Exception;
use Hodor\Config\LoaderFactory;

class JobQueue
{
    /**
     * @var string
     */
    private $config_file;

    /**
     * @var LoaderFactory
     */
    private $config;

    /**
     * @var QueueFactory
     */
    private $queue_factory;

    /**
     * @param string $job_name the name of the job to run
     * @param array $params the parameters to pass to the job
     * @param array $options the options to use when running the job
     */
    public function push($job_name, array $params = [], array $options = [])
    {
        $buffer_queue = $this->getQueueFactory()->getBufferQueueForJob(
            $job_name,
            $params,
            $options
        );

        $buffer_queue->push(
            $job_name,
            $params,
            $options
        );
    }

    /**
     * @param string $config_file
     */
    public function setConfigFile($config_file)
    {
        $this->config_file = $config_file;
    }

    /**
     * @return Config
     * @throws Exception
     */
    public function getConfig()
    {
        if ($this->config) {
            return $this->config;
        }

        if ($this->config_file) {
            $config_loader_factory = new LoaderFactory();
            $this->config = $config_loader_factory->loadFromFile($this->config_file);
        } else {
            throw new Exception(
                "Config could not be found or generated by JobQueueFacade."
            );
        }

        return $this->config;
    }

    /**
     * @param QueueFactory $queue_factory
     */
    public function setQueueFactory(QueueFactory $queue_factory)
    {
        $this->queue_factory = $queue_factory;
    }

    /**
     * @return QueueFactory
     */
    private function getQueueFactory()
    {
        if ($this->queue_factory) {
            return $this->queue_factory;
        }

        $this->queue_factory = new QueueFactory($this->getConfig());

        return $this->queue_factory;
    }
}
