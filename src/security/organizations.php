<?php

namespace security;

use dal_base;
use exceptions\db_exception;

class organizations extends dal_base {

    #region properties
    private static self $instance;
    #endregion

    #region public static function get_instance(): self
    public static function get_instance(): self {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    #endregion

    #region private function __construct(...)
    private function __construct() {
        parent::__construct();
    }
    #endregion

    #region public function add(...): int;
    /**
     * Adds a record to organizations table
     *
     * @param int|null $parent_id Parent node in organization tree
     * @param string $name organization name
     * @param string $description organization display name
     * @param string $created_by user who created this node
     * @return int id of this record
     * @throws db_exception
     */
    public function add(int|null $parent_id, string $name, string $description, string $created_by): int {
        try {
            $sql = 'INSERT INTO organizations(
                parent_id, name, description, created_by
            ) VALUES (
                :parent_id, :name, :description, :created_by
            )';
            return $this->execute_with_auto_id($sql, [
                ':parent_id'   => $parent_id,
                ':name'        => mb_strimwidth($name, 0, 32),
                ':description' => mb_strimwidth($description, 0, 100),
                ':created_by'  => mb_strimwidth($created_by, 0, 32)
            ]);
        } catch (\Exception $ex) {
            // 23000: duplicate record
            if ($ex instanceof \PDOException && $ex->getCode() === '23000')
                throw new db_exception('##ERROR_RECORD_EXIST##', intval($ex->getCode()), $ex);
            throw new db_exception('##ERROR_ADDING_RECORD_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function edit(...): int
    /**
     * edits a record in organizations table
     *
     * @param int $id Organization ID
     * @param int|null $parent_id Parent node in organization tree
     * @param string $name organization name
     * @param string $description organization display name
     * @param string $modified_by last user who modified this node
     * @return int count of affected rows
     * @throws db_exception
     */
    public function edit(int $id, int|null $parent_id, string $name, string $description, string $modified_by): int {
        try {
            $sql = 'UPDATE organizations SET
                parent_id   = :parent_id,
                name        = :name,
                description = :description,
                modified_by = :modified_by,
                date_modified = now()
            WHERE
                id          = :id';
            return $this->execute_non_query($sql, [
                ':parent_id'   => $parent_id,
                ':name'        => mb_strimwidth($name, 0, 32),
                ':description' => mb_strimwidth($description, 0, 100),
                ':modified_by' => mb_strimwidth($modified_by, 0, 32),
                ':id'          => $id
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function get(...): array
    /**
     * Gets a record from "organizations" table
     *
     * @param int $id Organization ID
     * @return organization|null returns null if record not found otherwise data record
     * @throws db_exception
     */
    public function get(int $id): organization|null {
        try {
            $sql = 'SELECT * FROM organizations WHERE id = :id';
            $result = $this->execute_query($sql, [':id' => $id]);
            if (count($result) == 1) {
                return organization::from_array($result[0]);
            }
            return null;
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function get_by_name(...): array
    /**
     * Gets a record from "organizations" table
     *
     * @param int $id Organization ID
     * @return organization|null returns null if record not found otherwise data record
     * @throws db_exception
     */
    public function get_by_name(string $name): organization|null {
        try {
            $sql = 'SELECT * FROM organizations WHERE name = :name';
            $result = $this->execute_query($sql, [':name' => $name]);
            if (count($result) == 1) {
                return organization::from_array($result[0]);
            }
            return null;
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function delete(...): int
    /**
     * deletes a record from "organizations" table
     *
     * @param int $id Organization ID
     * @return int count of affected rows
     * @throws db_exception
     */
    public function delete(int $id): int {
        try {
            $sql = 'DELETE FROM organizations WHERE 
                      id = :id AND
                      NOT EXISTS(SELECT 1 FROM users WHERE organization_id  = :id) AND
                      NOT EXISTS(SELECT 1 FROM roles WHERE organization_id  = :id) AND
                      NOT EXISTS(SELECT 1 FROM organizations WHERE parent_id  = :id)
                      ';
            return $this->execute_non_query($sql, [
                ':id' => $id
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_DELETING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function list_by_parent_id(...): array
    /**
     * searches for records in "organizations" table
     *
     * @param int $parent_id Parent node in organization tree
     * @return array of rows and count of records found
     * @throws db_exception
     */
    public function list_by_parent_id(int $parent_id): array {
        try {
            $sql = 'SELECT * FROM organizations WHERE parent_id = :parent_id';
            $exec_params = [
                ':parent_id' => $parent_id
            ];
            $result = $this->execute_query($sql, $exec_params);
            foreach ($result as $k => $record) {
                $result[$k] = organization::from_array($record);
            }
            return $result;
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function tree_by_parent_id(...): array
    /**
     * return organizaion tree by id of root node
     *
     * @param int|null $id id of root node in organization tree
     * @return array of rows and count of records found
     * @throws db_exception
     */
    public function tree_by_parent_id(int|null $id): array {
        try {
            if ($id == 0 || $id == null) {
                $sql = 'SELECT * FROM organizations ORDER BY name';
                $exec_params = [];
            } else {
                $sql = "WITH Recursive tree AS ( 
                            SELECT * FROM organizations WHERE id = :id 
                            UNION ALL 
                            SELECT child.* FROM organizations AS child JOIN tree AS parent ON child.parent_id = parent.Id 
                        ) 
                        SELECT * FROM tree";
                $exec_params = [
                    ':id' => $id
                ];
            }
            $result = $this->execute_query($sql, $exec_params);
            $records = [];
            foreach ($result as $k => $record) {
                $records[$record['id']] = organization::from_array($record);
            }
            return $records;
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function full_name(...): array
    /**
     * return full name of organization as domain
     *
     * @param int $id node id
     * @return string name of node in domain format
     * @throws db_exception
     */
    public function full_name(int $id): string {
        try {
            if ($id == 0)
                return '##ROOT##';
            else
                $sql = 'WITH Recursive tree AS ( 
                    SELECT id, parent_id, name, name as node FROM organizations WHERE id = :id 
                    UNION ALL 
                    
                    SELECT child.id, child.parent_id, child.name, concat(node, \'.\',child.name) as node 
                    FROM organizations AS child JOIN tree AS parent ON parent.parent_id = child.Id 
                ) 
                SELECT node FROM tree WHERE parent_id=0;';
            $exec_params = [
                ':id' => $id
            ];
            $result = $this->execute_query($sql, $exec_params);
            if (count($result) == 1)
                return preg_replace('/\.root$/', '', $result[0]['node']);
            return '';
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function move(...): array
    /**
     * moves and organization from one parent to another parent
     *
     * @param int $target moved organization id
     * @param int $dest destination organization id
     * @return int count of affected rows
     * @throws db_exception
     */
    public function move(int $target, int $dest, string $modified_by): int {
        try {
            $sql = 'UPDATE ignore organizations SET
                parent_id   = :parent_id,
                modified_by = :modified_by,
                date_modified = now()
            WHERE
                id          = :id';
            return $this->execute_non_query($sql, [
                ':parent_id'   => $dest,
                ':modified_by' => mb_strimwidth($modified_by, 0, 32),
                ':id'          => $target
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

}
