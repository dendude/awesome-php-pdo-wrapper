<?php
namespace Database;

use PDO;

/**
 * Simple wrapper for queries to PDO
 *
 * Class DB
 * @package Database
 */
class DB extends PDO {

    const CONFIG_DEFAULT = 'default';
    const CONFIG_OTHER = 'other';

    const CONFIG_DEFAULT_TEST = 'default_test';
    const CONFIG_OTHER_TEST = 'other_test';

    const QUERY_IS_NULL = 'IS NULL';
    const QUERY_IS_NOT_NULL = 'IS NOT NULL';

    protected $preparedQuery;
    protected $preparedPlaceholders = [];

    protected static $_unit_test = false;
    protected static $_transactionCounters = [];

    protected const QUERY_COUNT = 'count';
    protected const QUERY_CELL = 'cell';
    protected const QUERY_COLUMN = 'column';
    protected const QUERY_SELECT = 'select';
    protected const QUERY_INSERT = 'insert';
    protected const QUERY_UPDATE = 'update';
    protected const QUERY_DELETE = 'delete';

    /**
     * Dictionary fo test databases
     * [production_db => test_db]
     *
     * @var array
     */
    protected static $_test_db_names = [
        self::CONFIG_DEFAULT => self::CONFIG_DEFAULT_TEST,
    ];

    /** @var $_instances PDO[] */
    protected static $_instances;

    /** @var $_config array */
    protected static $_config;

    /**
     * connect to the db
     *
     * @param string $db_name
     * @param null   $server
     * @return DB|PDO
     * @throws \Exception
     */
    public static function getInstance($db_name = self::CONFIG_DEFAULT, $server = null) {

        if (self::$_unit_test === true && in_array($db_name, array_keys(self::$_test_db_names))) {
            // unit-testing enabled
            $db_name = self::$_test_db_names[$db_name];
        }

        $instance_key = $db_name . '_' . $server;

        // instance exist
        if (isset(self::$_instances[$instance_key])) {
            return self::$_instances[$instance_key];
        }

        // get instance config
        if (self::$_config === null) {
            self::$_config = self::_getConfig();
        }

        $db_config = self::$_config[$db_name];
        $dsn = self::_getDSN($db_config, $server);

        try {
            self::$_instances[$instance_key] = null;
            self::$_instances[$instance_key] = new self($dsn, $db_config['user'], $db_config['pass']);
            self::$_instances[$instance_key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            error_log('PDO exception START');
            error_log($e->getMessage());
            error_log($dsn);
            error_log('PDO exception FINISH');
            throw $e;
        }
        
        return self::$_instances[$instance_key];
    }

    /**
     * get current instance of database connection
     * 
     * @return int|null|string
     */
    protected function _getCurrentInstance() {

        if (isset(self::$_instances) && is_array(self::$_instances)) {
            foreach (self::$_instances AS $instance_key => $instance) {
                if ($instance === $this) return $instance_key;
            }
        }

        return null;
    }

    /**
     * begin of transaction for list of the queries
     * 
     * @example
     *
     * $db->beginTransaction();
     *
     * try {
     *   // commit queries
     * } catch (Exception $e) {
     *   // rollback
     * }
     * 
     * @return bool|null
     */
    public function beginTransaction() {

        $current_instance_key = $this->_getCurrentInstance();

        if (!isset(self::$_transactionCounters[$current_instance_key])) {
            self::$_transactionCounters[$current_instance_key] = 0;
        }

        // transaction counters for each transaction instance
        self::$_transactionCounters[$current_instance_key]++;

        if (self::$_transactionCounters[$current_instance_key] == 1) {
            // begin transaction only for first call
            return parent::beginTransaction();
        }

        return null;
    }

    /**
     * commit of the transaction for list of the queries
     * 
     * @example 
     * $db->beginTransaction();
     * try {
     *   $db->insert(...);
     *   $db->insert(...);
     *   $db->update(...);
     *
     *   $db->commit();
     *
     * } catch (Exception $e) {
     *   // rollback
     * }
     * 
     * @return bool|null
     */
    public function commit() {

        // break the empty transaction
        if (!$this->inTransaction()) return null;

        $current_instance_key = $this->_getCurrentInstance();

        // increment of counter for current instance
        if (isset(self::$_transactionCounters[$current_instance_key])) {
            self::$_transactionCounters[$current_instance_key]--;

            if (self::$_transactionCounters[$current_instance_key] == 0) {
                // commit only for first call of the instance
                return parent::commit();
            }
        }

        return null;
    }

    /**
     * rollback of the transaction for list of the queries
     *
     * @example
     * $db->beginTransaction();
     * try {
     *   // commit queries
     * } catch (Exception $e) {
     *
     *   $db->rollback();
     *
     * }
     * 
     * @return bool|null
     */
    public function rollBack() {

        // break the empty transaction
        if (!$this->inTransaction()) return null;

        $current_instance_key = $this->_getCurrentInstance();
        
        // reset counter of current instance
        unset(self::$_transactionCounters[$current_instance_key]);

        return parent::rollBack();
    }

    /**
     * set unit-tests mode
     * 
     * @example DB::setUnitTestMode(true);
     * 
     * @param bool $mode
     */
    public static function setUnitTestMode(bool $mode) {
        self::$_unit_test = $mode;
    }

    /**
     * close all the connections
     * 
     * @example DB::closeConnections();
     */
    public static function closeConnections() {

        if (is_array(self::$_instances) && count(self::$_instances)) {
            foreach (self::$_instances AS $dsn => $instance) {
                unset(self::$_instances[$dsn]);
            }
        }

        self::$_instances = null;
    }

    /**
     * select all the rows of the table
     *
     * @param        $table
     * @param array  $conditions
     * @param null   $order
     * @param null   $limit
     *
     * $db->selectAll('users', ['role' => 'manager']);
     *
     * @return array|null
     */
    public function selectAll($table, array $conditions = [], $order = null, $limit = null) {

        $this->prepareData(self::QUERY_SELECT, $table, null, $conditions, $order, $limit);

        $stmt = $this->prepare($this->preparedQuery);
        $stmt->execute($this->preparedPlaceholders);

        return $stmt->rowCount() ? $stmt->fetchAll(PDO::FETCH_ASSOC) : null;
    }

    /**
     * @param $table
     * @param $id
     * @return mixed|null
     */
    public function selectRowById($table, $id) {
        return $this->selectRow($table, ['id' => $id]);
    }

    /**
     * select one row of the table
     *
     * @param $table
     * @param array $conditions
     * @param null $order
     * @return mixed|null
     */
    public function selectRow($table, array $conditions, $order = null) {

        $this->prepareData(self::QUERY_SELECT, $table, null, $conditions, $order);

        $stmt = $this->prepare($this->preparedQuery);
        $stmt->execute($this->preparedPlaceholders);

        return $stmt->rowCount() ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    }

    /**
     * select column as array
     *
     * @param $table
     * @param $column
     * @param array $conditions
     * @param null $order
     * 
     * @example $db->selectColumn('users', 'name', ['role' => 'user']);
     * 
     * @return array|null
     */
    public function selectColumn($table, $column, array $conditions = [], $order = null) {

        $this->prepareData(self::QUERY_COLUMN, $table, [$column], $conditions, $order);

        $stmt = $this->prepare($this->preparedQuery);
        $stmt->execute($this->preparedPlaceholders);

        return $stmt->rowCount() ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : null;
    }

    /**
     * select one cell of the row in the table
     *
     * @param $table
     * @param $column
     * @param array $conditions
     * @param null $order
     * 
     * @example $db->selectCell('users', 'email', ['id' => 2]);
     *
     * @return null|string
     */
    public function selectCell($table, $column, array $conditions = [], $order = null) {

        $this->prepareData(self::QUERY_CELL, $table, [$column], $conditions, $order);

        $stmt = $this->prepare($this->preparedQuery);
        $stmt->execute($this->preparedPlaceholders);

        return $stmt->rowCount() ? $stmt->fetchColumn() : null;
    }

    /**
     * select count of rows
     *
     * @param       $table
     * @param array $conditions
     * 
     * @example $db->selectCount('users', ['role' => 'manager']);
     *
     * @return int
     */
    public function selectCount($table, array $conditions = []) {

        $this->prepareData(self::QUERY_COUNT, $table, null, $conditions);

        $stmt = $this->prepare($this->preparedQuery);
        $stmt->execute($this->preparedPlaceholders);

        return $stmt->rowCount() ? $stmt->fetchColumn() : 0;
    }

    /**
     * insert a data
     *
     * @param string $table
     * @param array  $params
     * 
     * @example $db->insert('users', ['role' => 'admin', 'name' => 'John Smith']);
     *
     * @return int|null
     */
    public function insert($table, array $params) {

        $this->prepareData(self::QUERY_INSERT, $table, $params);

        $stmt = $this->prepare($this->preparedQuery);
        $result = $stmt->execute($this->preparedPlaceholders);

        return $result ? (int)$this->lastInsertId() : null;
    }

    /**
     * update the rows
     *
     * @param string $table
     * @param array  $params
     * @param array  $conditions
     *
     * @example $db->update('users', ['role' => 'admin'], ['id' => 1]);
     *
     * @return bool
     */
    public function update($table, array $params, array $conditions = []) {

        $this->prepareData(self::QUERY_UPDATE, $table, $params, $conditions);

        $stmt = $this->prepare($this->preparedQuery);
        return $stmt->execute($this->preparedPlaceholders);
    }

    /**
     * update some counters in the table
     *
     * @param string $table
     * @param array  $counters
     * @param array  $conditions
     *
     * @example $db->updateCounters('visits', ['visit' => 1], ['user_id' => 2])
     *
     * @return bool
     */
    public function updateCounters($table, array $counters, array $conditions = []) {

        $params = [];
        foreach ($counters AS $field => $inc) {
            $inc = is_numeric($inc) ? $inc : 0;
            $params[$field] = new DBExpression("`{$field}` + {$inc}");
        }

        $this->prepareData(self::QUERY_UPDATE, $table, $params, $conditions);
        
        $stmt = $this->prepare($this->preparedQuery);
        return $stmt->execute($this->preparedPlaceholders);
    }

    /**
     * check of the existing row
     *
     * @param string $table
     * @param array  $conditions
     *
     * @example $db->exists('users', ['role' => 'admin']);
     *
     * @return bool
     */
    public function exists($table, array $conditions = []) {

        $this->prepareData(self::QUERY_COUNT, $table, null, $conditions, null, '1');

        $stmt = $this->prepare($this->preparedQuery);
        $stmt->execute($this->preparedPlaceholders);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * delete rows
     *
     * @example $db->delete('users', ['visits' => 0]);
     *
     * @param       $table
     * @param array $conditions
     *
     * @return bool
     */
    public function delete($table, array $conditions = []) {

        $this->prepareData(self::QUERY_DELETE, $table, null, $conditions);

        $stmt = $this->prepare($this->preparedQuery);
        return $stmt->execute($this->preparedPlaceholders);
    }

    /**
     * get config from json-file
     *
     * @return mixed
     */
    protected static function _getConfig() {
        $config = file_get_contents(__DIR__ . '/../config/db.json');
        return json_decode($config, true);
    }

    /**
     * get DSN string for connect to the database
     *
     * @param      $config
     * @param null $server
     * @return string
     */
    protected static function _getDSN($config, $server = null) {

        if (isset($server)) {
            // for bases on the difference servers
            $config['host'] = str_replace('{server}', $server, $config['host']);
        }

        return "mysql:dbname={$config['name']};host={$config['host']};charset=utf8";
    }

    /**
     * basic prepare of the data
     *
     * @param string      $type
     * @param string      $table
     * @param array|null  $params
     * @param array|null  $conditions
     * @param string|null $order
     * @param string|null $limit
     */
    protected function prepareData(string $type, string $table, array $params = null, array $conditions = null, string $order = null, string $limit = null) {

        switch ($type) {

            case self::QUERY_INSERT:
                $query = "INSERT INTO `{$table}`";
                break;

            case self::QUERY_UPDATE:
                $query = "UPDATE `{$table}`";
                break;

            case self::QUERY_DELETE:
                $query = "DELETE FROM `{$table}`";
                break;

            case self::QUERY_COUNT:
                $query = "SELECT COUNT(*) FROM `{$table}`";
                break;

            case self::QUERY_CELL:
                $query = "SELECT {$params[0]} FROM `{$table}`";
                $limit = 1;
                break;

            case self::QUERY_COLUMN:
                $query = "SELECT {$params[0]} FROM `{$table}`";
                break;

            default:
                $query = "SELECT * FROM `{$table}`";
        }

        $placeholders = [];

        switch ($type) {
            case self::QUERY_INSERT:
            case self::QUERY_UPDATE:

                if (count($params) === 0) break;

                $preparedData = self::prepareSetParams($params);

                $query .= ' SET ' . implode(', ', $preparedData['set']);
                $placeholders = array_merge($placeholders, $preparedData['placeholders']);
                break;
        }

        if (count($conditions) > 0) {
            $preparedData = self::prepareConditionsParams($conditions);

            $query .= ' WHERE ' . implode(', ', $preparedData['where']);
            $placeholders = array_merge($placeholders, $preparedData['placeholders']);
        }

        if (isset($order)) $query .= ' ORDER BY ' . $order;
        if (isset($limit)) $query .= ' LIMIT ' . $limit;

        $this->preparedQuery = $query;
        $this->preparedPlaceholders = $placeholders;
    }

    /**
     * prepare of the setting params for insert/update queries
     *
     * @param array $params
     * @return array
     */
    protected static function prepareSetParams(array $params) {

        $set = [];

        foreach ($params as $key => $value) {

            switch (true) {

                case ($value instanceof DBExpression):
                    $set[] = "`{$key}` = " . $value->getValue();
                    unset($params[$key]);
                    break;

                case is_null($value):
                    $where[] = "`{$key}` = NULL";
                    unset($params[$key]);
                    break;

                default:
                    $set[] = "`{$key}` = ?";
                    break;
            }
        }

        return [
            'set' => $set,
            'placeholders' => array_values($params)
        ];
    }

    /**
     * prepare of the conditions for query
     *
     * @param array $conditions
     * @return array
     */
    protected static function prepareConditionsParams(array $conditions) {

        $where = [];

        foreach ($conditions as $key => $value) {

            switch (true) {

                case is_null($value):
                case strtoupper($value) === self::QUERY_IS_NULL:
                    $where[] = "`{$key}` " . self::QUERY_IS_NULL;
                    unset($conditions[$key]);
                    break;

                case strtoupper($value) === self::QUERY_IS_NOT_NULL:
                    $where[] = "`{$key}` " . self::QUERY_IS_NOT_NULL;
                    unset($conditions[$key]);
                    break;

                default:
                    $where[] = "`{$key}` = ?";
                    break;
            }
        }

        return [
            'where' => $where,
            'placeholders' => array_values($conditions)
        ];
    }
}
