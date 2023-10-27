<?php

use config\config;
use exceptions\db_exception;
use logger\logger;

class dal_base {

    #region properties
    private \PDO $pdo;
    #endregion

    #region protected function __construct(...)
    /**
     * constructor
     *
     * @throws db_exception
     */
    protected function __construct() {
        $logger = logger::get_instance();
        try {

            $this->pdo = new \PDO(
                'mysql:host=' . config::MYSQL_HOST . ';dbname=' . config::MYSQL_DATABASE . ';port=' . config::MYSQL_PORT . ';charset=utf8mb4', config::MYSQL_USERNAME, config::MYSQL_PASSWORD, [
                //\PDO::ATTR_EMULATE_PREPARES   => false,                   // turn off emulation mode for 'real' prepared statements
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,   //turn on errors in the form of exceptions
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,         //make the default fetch be an associative array
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_CANNOT_CONNECT_TO_DATABASE##', 0, $ex);
        }
    }
    #endregion

    #region public function __destruct()
    /**
     * destructor
     */
    public function __destruct() {
        unset($this->pdo);
    }
    #endregion

    #region protected function execute_non_query(...): array
    protected function execute_non_query(string $sql, array $params): int {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }
    #endregion

    #region protected function execute_with_auto_id(...): array
    /**
     * @param string $sql
     * @param array $params
     * @return int
     */
    protected function execute_with_auto_id(string $sql, array $params): int {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $this->pdo->lastInsertId();
    }
    #endregion

    #region protected function execute_query(...): array
    /**
     * @param string $sql
     * @param array $params
     * @param int $limit
     * @param int $offset
     * @return array
     */
    protected function execute_query(string $sql, array $params, int $limit = -1, int $offset = -1): array {
        if ($limit !== -1) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset !== -1) {
                $sql .= ' OFFSET ' . $offset;
            }
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
    #endregion

}