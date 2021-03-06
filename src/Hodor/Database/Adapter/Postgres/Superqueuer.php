<?php

namespace Hodor\Database\Adapter\Postgres;

use Generator;
use Hodor\Database\Adapter\SuperqueuerInterface;
use Lstr\YoPdo\YoPdo;

class Superqueuer implements SuperqueuerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $category
     * @param string $name
     * @return bool
     */
    public function requestAdvisoryLock($category, $name)
    {
        $category_crc = crc32($category) - 0x80000000;
        $name_crc = crc32($name) - 0x80000000;

        $row = $this->getYoPdo()->query(
            'SELECT pg_try_advisory_lock(:category_crc, :name_crc) AS is_granted',
            [
                'category_crc' => $category_crc,
                'name_crc'     => $name_crc,
            ]
        )->fetch();

        return $row['is_granted'];
    }

    /**
     * @return Generator
     */
    public function getJobsToRunGenerator()
    {
        $this->processScheduledJobs();

        $sql = <<<SQL
WITH mutexed_buffered_jobs AS (
    SELECT DISTINCT ON (mutex_id)
        buffered_jobs.*
    FROM buffered_jobs
    ORDER BY
        mutex_id,
        job_rank,
        buffered_at
)
SELECT *
FROM mutexed_buffered_jobs
WHERE mutex_id NOT IN (
        SELECT queued_jobs.mutex_id
        FROM queued_jobs
    )
ORDER BY
    job_rank,
    buffered_at,
    buffered_job_id
SQL;

        $row_generator = $this->getYoPdo()->getSelectRowGenerator($sql);
        foreach ($row_generator as $job) {
            $job['job_params'] = json_decode($job['job_params'], true);
            yield $job;
        }
    }

    public function beginBatch()
    {
        $this->getYoPdo()->transaction()->begin('superqueue-jobs');
    }

    /**
     * @param array $job
     * @return array
     */
    public function markJobAsQueued(array $job)
    {
        $this->getYoPdo()->delete(
            'buffered_jobs',
            'buffered_job_id = :buffered_job_id',
            ['buffered_job_id' => $job['buffered_job_id']]
        );
        $job['job_params'] = json_encode($job['job_params'], JSON_FORCE_OBJECT);
        $job['superqueued_from'] = gethostname();
        $this->getYoPdo()->insert(
            'queued_jobs',
            [
                'buffered_job_id'  => $job['buffered_job_id'],
                'queue_name'       => $job['queue_name'],
                'job_name'         => $job['job_name'],
                'job_params'       => $job['job_params'],
                'job_rank'         => $job['job_rank'],
                'run_after'        => $job['run_after'],
                'buffered_at'      => $job['buffered_at'],
                'buffered_from'    => $job['buffered_from'],
                'inserted_at'      => $job['inserted_at'],
                'inserted_from'    => $job['inserted_from'],
                'superqueued_from' => $job['superqueued_from'],
                'mutex_id'         => $job['mutex_id'],
            ]
        );

        return ['buffered_job_id' => $job['buffered_job_id']];
    }

    public function publishBatch()
    {
        $this->getYoPdo()->transaction()->accept('superqueue-jobs');
    }

    private function processScheduledJobs()
    {
        $sql = <<<SQL
INSERT INTO buffered_jobs
(
    buffered_job_id,
    queue_name,
    job_name,
    job_params,
    job_rank,
    run_after,
    buffered_at,
    buffered_from,
    inserted_at,
    inserted_from,
    mutex_id,
    scheduled_at,
    scheduled_from
)
SELECT
    buffered_job_id,
    queue_name,
    job_name,
    job_params,
    job_rank,
    run_after,
    buffered_at,
    buffered_from,
    NOW(),
    :inserted_from,
    mutex_id,
    scheduled_at,
    scheduled_from
FROM scheduled_jobs
WHERE run_after <= NOW();

DELETE FROM scheduled_jobs
WHERE run_after <= NOW();
SQL;

        $this->getYoPdo()->transaction()->begin('scheduled-jobs');
        $this->getYoPdo()->queryMultiple($sql, ['inserted_from' => gethostname()]);
        $this->getYoPdo()->transaction()->accept('scheduled-jobs');
    }

    /**
     * @return YoPdo
     */
    private function getYoPdo()
    {
        return $this->connection->getYoPdo();
    }
}
