<?php

namespace Hodor\Database\Driver;

use Generator;
use Lstr\YoPdo\Factory as YoPdoFactory;
use Lstr\YoPdo\YoPdo;
use PDOStatement;

class YoPdoDriver
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var YoPdo
     */
    private $yo_pdo;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $sql
     * @return PDOStatement
     */
    public function queryMultiple($sql)
    {
        return $this->getYoPdo()->queryMultiple($sql);
    }

    /**
     * @param  string $sql
     * @return Generator
     */
    public function selectRowGenerator($sql)
    {
        $result = $this->getYoPdo()->query($sql);

        while ($row = $result->fetch()) {
            yield $row;
        }
    }

    /**
     * @param  string $sql
     * @param  array $params
     * @return callable
     */
    public function selectOne($sql, $params = array())
    {
        $result = $this->getYoPdo()->query($sql, $params);

        return $result->fetch();
    }

    /**
     * @param string $table
     * @param array $row
     * @return void
     */
    public function insert($table, array $row)
    {
        $this->getYoPdo()->insert($table, $row);
    }

    /**
     * @param string $table
     * @param string $where_sql
     * @param array $values
     * @return void
     */
    public function delete($table, $where_sql, array $values)
    {
        $this->getYoPdo()->delete($table, $where_sql, $values);
    }

    /**
     * @return YoPdo
     */
    public function getYoPdo()
    {
        if ($this->yo_pdo) {
            return $this->yo_pdo;
        }

        $factory = new YoPdoFactory();
        $this->yo_pdo = $factory->createFromConfig($this->config);

        return $this->yo_pdo;
    }
}
