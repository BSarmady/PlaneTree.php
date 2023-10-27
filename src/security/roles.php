<?php

namespace security;

use dal_base;
use exceptions\db_exception;

class roles extends dal_base {

    #region properties
    private static roles $instance;
    #endregion

    #region public static function get_instance(): static
    public static function get_instance(): static {
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
     * Adds a record to roles table
     *
     * @param string $name Name of the role
     * @param int $organization_id organization id
     * @param string $description Description of role
     * @param int $visible_below Is this role visible to organization subtree below
     * @param string $permissions Permissions of this role
     * @param string $created_by Use who has created this role
     * @return int count of affected rows
     * @throws db_exception
     */
    public function add(string $name, int $organization_id, string $description, int $visible_below, string $permissions, string $created_by): int {
        try {
            if ($name === Role::guests()->name || $name === Role::root_admin()->name)
                throw new \Exception('##ERROR_RECORD_EXIST##');
            $sql = "INSERT INTO roles(
                name, organization_id, description, visible_below, permissions, created_by
            ) VALUES (
                :name, :organization_id, :description, :visible_below, :permissions, :created_by
            )";
            return $this->execute_non_query($sql, [
                ':name'            => mb_strimwidth($name, 0, 32),
                ':organization_id' => $organization_id,
                ':description'     => mb_strimwidth($description, 0, 100),
                ':visible_below'   => $visible_below,
                ':permissions'     => $permissions,
                ':created_by'      => mb_strimwidth($created_by, 0, 32)
            ]);
        } catch (\Exception $ex) {
            // 22001: truncated data, 23000: duplicate record
            if ($ex instanceof \PDOException && in_array($ex->getCode(), ['22001', '23000']))
                return 0;
            throw new db_exception('##ERROR_ADDING_RECORD_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function edit(...): int
    /**
     * edits a record in roles table
     *
     * @param string $name Name of the role
     * @param int $organization_id organization id
     * @param string $description Description of role
     * @param int $visible_below Is this role visible to organization subtree below
     * @param string $permissions Permissions of this role
     * @param string $modified_by Last user who has modified the record
     * @return int count of affected rows
     * @throws db_exception
     */
    public function edit(string $name, int $organization_id, string $description, int $visible_below, string $permissions, string $modified_by): int {
        try {
            if ($name === Role::guests()->name || $name === Role::root_admin()->name)
                throw new \Exception('##ERROR_CANNOT_EDIT_BUILT_IN_ROLES##');
            // if you like to keep previous value of column when value of param is empty,
            //     use following sql code IF (:col = '', col, :col),
            $sql = "UPDATE roles SET
                name            = :name,
                organization_id = :organization_id,
                description     = :description,
                visible_below   = :visible_below,
                permissions     = :permissions,
                modified_by     = :modified_by,
                date_modified   = now()
            WHERE
                name            = :name";
            return $this->execute_non_query($sql, [
                ':name'            => mb_strimwidth($name, 0, 32),
                ':organization_id' => $organization_id,
                ':description'     => mb_strimwidth($description, 0, 100),
                ':visible_below'   => $visible_below,
                ':permissions'     => $permissions,
                ':modified_by'     => mb_strimwidth($modified_by, 0, 32)
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function get(...): array
    /**
     * Gets a record from "roles" table
     *
     * @param string $name Name of the role
     * @return role|null returns null if record not found otherwise data record
     * @throws db_exception
     */
    public function get(string $name): role|null {
        try {
            if ($name === Role::guests()->name)
                return Role::guests();
            if ($name === Role::root_admin()->name)
                return Role::root_admin();

            $sql = "SELECT * FROM roles WHERE name = :name";
            $result = $this->execute_query($sql, [':name' => $name]);
            if (count($result) == 1) {
                return role::from_array($result[0]);
            }
            return null;
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function delete(...): int
    /**
     * deletes a record from "roles" table
     *
     * @param string $name Name of the role
     * @return int count of affected rows
     * @throws db_exception
     */
    public function delete(string $name): int {
        try {
            if ($name === Role::guests()->name || $name === Role::root_admin()->name)
                throw new \Exception('##ERROR_CANNOT_DELETE_BUILT_IN_ROLES##');
            $sql = "DELETE FROM roles WHERE name = :name AND NOT EXISTS(SELECT 1 FROM users WHERE roles like concat('%;', :name, ';%'))";
            return $this->execute_non_query($sql, [
                ':name' => $name
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_DELETING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function list_for_current_node(...):array
    public function list_for_current_node(int $organization_id): array|null {
        try {
            // MariaDb doesn't support multiple recursive yet
            if ($organization_id === 0) {
                $sql = "SELECT * FROM roles";
                $exec_params = [];
            } else {
                $sql = "SELECT * FROM
                    (WITH Recursive tree AS (
                            SELECT * FROM organizations WHERE id = :organization_id 
                            UNION ALL 
                            SELECT parent.* FROM organizations AS parent JOIN tree AS child ON parent.id = child.parent_Id
                        ) 
                        SELECT roles.* FROM roles WHERE roles.organization_id in (select id from tree) AND visible_below = 1
                    ) AS TREE1
                    UNION
                    SELECT * FROM
                    (WITH Recursive tree AS (
                            SELECT * FROM organizations WHERE id = :organization_id 
                            UNION ALL
                            SELECT child.* FROM organizations AS child JOIN tree AS parent ON child.parent_id = parent.Id
                        ) 
                        SELECT roles.* FROM roles WHERE roles.organization_id in (select id from tree)
                    ) AS TREE2";
                $exec_params = [':organization_id' => $organization_id];
            }

            $result = $this->execute_query($sql, $exec_params);
            $records = [];
            foreach ($result as $record) {
                $records[$record['name']] = role::from_array($record);
            }
            return $records;
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

}
