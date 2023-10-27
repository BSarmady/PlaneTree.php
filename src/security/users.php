<?php

namespace security;

use config\config;
use dal_base;
use Exception;
use exceptions\db_exception;

class users extends dal_base {

    private static users $instance;

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
     * Adds a record to users table
     *
     * @param int $organization_id Organization ID
     * @param string $account_type
     * @param string $username
     * @param string $real_name
     * @param string $mobile_no
     * @param string $email
     * @param string $password
     * @param string $password_salt
     * @param string $roles
     * @param string $created_by
     * @param string $comment
     * @return int count of affected rows
     * @throws db_exception
     */
    public function add(int    $organization_id, string $account_type, string $username, string $real_name,
                        string $mobile_no, string $email, string $password, string $password_salt,
                        string $roles, string $created_by, string $comment): int {
        try {
            $sql = 'INSERT INTO users(
                organization_id, account_type, username, real_name, mobile_no, email, 
                password, password_salt, roles, status, created_by, comment
            ) VALUES (
                :organization_id, :account_type, :username, :real_name, :mobile_no, :email,
                :password, :password_salt, :roles, \'initial\', :created_by, :comment
            )';
            return $this->execute_non_query($sql, [
                ':organization_id' => $organization_id,
                ':account_type'    => $account_type,
                ':username'        => mb_strimwidth($username, 0, 32),
                ':real_name'       => mb_strimwidth($real_name, 0, 50),
                ':mobile_no'       => mb_strimwidth($mobile_no, 0, 16),
                ':email'           => mb_strimwidth($email, 0, 50),
                ':password'        => $password,
                ':password_salt'   => $password_salt,
                ':roles'           => mb_strimwidth($roles, 0, 1024),
                ':created_by'      => mb_strimwidth($created_by, 0, 32),
                ':comment'         => mb_strimwidth($comment, 0, 1024),
            ]);
        } catch (Exception $ex) {
            // 01000: invalid enum data, 23000: duplicate record
            if ($ex instanceof \PDOException && $ex->getCode() == '23000')
                return 0;
            throw new db_exception('##ERROR_ADDING_RECORD_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function admin_edit(...): int
    /**
     * edits a record in users table
     *
     * @param string $username
     * @param int $organization_id Organization ID
     * @param string $account_type
     * @param string $real_name
     * @param string $mobile_no
     * @param string $email
     * @param string|null $password
     * @param string|null $password_salt
     * @param string $roles
     * @param string $modified_by
     * @param string $comment
     * @return int count of affected rows
     * @throws db_exception
     */
    public function admin_edit(string $username, int $organization_id, string $account_type, string $real_name,
                               string $mobile_no, string $email, string|null $password, string|null $password_salt,
                               string $roles, string $modified_by, string $comment): int {
        try {
            // if you like to keep previous value of column when value of param is empty,
            //     use following sql code IF (:col = '', col, :col),
            $sql = 'UPDATE users SET
                organization_id  = :organization_id,
                account_type     = :account_type,
                real_name        = :real_name,
                mobile_no        = :mobile_no,
                email            = :email,
                password         = IF (:password IS NULL, password, :password),
                password_salt    = IF (:password IS NULL, password_salt, :password_salt ),
                date_pwd_changed = IF (:password IS NULL, date_pwd_changed, \'2010-10-10\'),
                roles            = :roles,
                modified_by      = :modified_by,
                comment          = :comment,
                date_modified    = now()
            WHERE
                username         = :username';

            return $this->execute_non_query($sql, [
                ':organization_id' => $organization_id,
                ':account_type'    => $account_type,
                ':real_name'       => mb_strimwidth($real_name, 0, 50),
                ':mobile_no'       => mb_strimwidth($mobile_no, 0, 16),
                ':email'           => mb_strimwidth($email, 0, 50),
                ':password'        => $password == '' ? NULL : $password,
                ':password_salt'   => $password_salt,
                ':roles'           => mb_strimwidth($roles, 0, 1024),
                ':modified_by'     => mb_strimwidth($modified_by, 0, 32),
                ':comment'         => mb_strimwidth($comment, 0, 1024),
                ':username'        => $username
            ]);
        } catch (Exception $ex) {
            // 01000: invalid enum data, 22001: truncated data
            if ($ex instanceof \PDOException && in_array($ex->getCode(), ['01000', '22001']))
                return 0;
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function get(...): array
    /**
     * Gets a record from "users" table
     *
     * @param string $username
     * @return user|null returns null if record not found otherwise user
     * @throws db_exception
     */
    public function get(string $username): user|null {
        try {
            $sql = 'SELECT * FROM users WHERE username = :username';
            $result = $this->execute_query($sql, [':username' => $username]);
            if (count($result) == 1) {
                return user::from_array($result[0]);
            }
            return null;
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function search(...): array
    /**
     * searches for records in "users" table
     *
     * @param int $organization_id organization id
     * @param string $search_phrase search phrase
     * @param bool $node_only return results from selected organization only
     * @return array of rows and count of records found
     * @throws db_exception
     */
    public function search(int $organization_id, string $search_phrase, bool $node_only): array {
        try {
            // $organization_id      $search_phrase      $node_only

            // 1: root                     no                 true         return node users
            // 2: others                   no                 true         return node users
            // 3: root                     yes                true         search in node users
            // 4: others                   yes                true         search in node users

            // 5: root                     no                 false        return all users
            // 6: root                     yes                false        search all users

            // 7: others                   no                 false        return users with tree
            // 8: others                   yes                false        search users with tree

            if ($node_only) {
                // 1,2
                $sql = "SELECT * FROM users WHERE organization_id = :organization_id";
                $params[':organization_id'] = $organization_id;
                if ($search_phrase != '') {
                    // 3,4
                    $sql .= " AND (
                            username LIKE :search_phrase OR 
                            mobile_no LIKE :search_phrase OR 
                            email LIKE :search_phrase
                        )";
                    $params[':search_phrase'] = '%' . $search_phrase . '%';
                }
            } else if ($organization_id == 1) {
                // 5
                $sql = "SELECT * FROM users";
                $params = [];
                if ($search_phrase != '') {
                    // 6
                    $sql .= " WHERE username LIKE :search_phrase OR 
                        mobile_no LIKE :search_phrase OR 
                        email LIKE :search_phrase";
                    $params[':search_phrase'] = '%' . $search_phrase . '%';
                }
            } else {
                //7
                $sql = "WITH Recursive tree AS ( 
                            SELECT * FROM organizations WHERE id = :organization_id
                            UNION ALL 
                            SELECT child.* FROM organizations AS child JOIN tree AS parent ON child.parent_id = parent.Id 
                        ) 
                    SELECT users.* FROM tree    
                    JOIN users ON users.organization_id = tree.Id";
                $params[':organization_id'] = $organization_id;
                if ($search_phrase != '') {
                    //8
                    $sql .= " WHERE
                        username LIKE :search_phrase OR 
                        mobile_no LIKE :search_phrase OR 
                        email LIKE :search_phrase";
                    $params[':search_phrase'] = '%' . $search_phrase . '%';
                }
            }
            $result = $this->execute_query($sql, $params, 5000);
            foreach ($result as $k => $record) {
                $result[$k] = user::from_array($record);
            }
            return $result;
        } catch
        (Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function delete(...): int
    /**
     * deletes a record from "users" table
     *
     * @param string $username
     * @return int count of affected rows
     * @throws db_exception
     */
    public function delete(string $username): int {
        try {
            $sql = "DELETE FROM users WHERE username = :username";
            return $this->execute_non_query($sql, [
                ':username' => $username
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_DELETING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function unlock(...): int
    /**
     * @param string $username
     * @param string $modified_by
     * @return int count of affected rows
     * @throws db_exception
     */
    public function unlock(string $username, string $modified_by): int {
        try {
            $sql = "UPDATE users SET 
                unique_login_id      = NULL,
                failed_sign_in_count = 0,
                date_locked_out      = NULL,
                status               = 'active',
                modified_by          = :modified_by,
                date_modified        = now()
            WHERE 
                username             = :username";
            return $this->execute_non_query($sql, [
                ':username'    => $username,
                ':modified_by' => $modified_by
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function update_sign_in(...): int
    /**
     * @param string $username username
     * @param bool $failed_attempt is this a failed login attempt
     * @param string $unique_login_id
     * @return int affected row count
     * @throws db_exception
     */
    public function update_sign_in(string $username, bool $failed_attempt, string $unique_login_id = ''): int {
        try {
            $sql = "UPDATE users SET 
                        unique_login_id      = :unique_login_id,
                        failed_sign_in_count = IF(:failed_attempt, IF(failed_sign_in_count >= :max_failed_attempt_count,:max_failed_attempt_count, failed_sign_in_count + 1), 0),
                        date_signed_in       = IF(:failed_attempt, date_signed_in, NOW()),
                        date_locked_out      = IF(failed_sign_in_count >= :max_failed_attempt_count, NOW(), date_locked_out),
                        status               = IF(failed_sign_in_count >= :max_failed_attempt_count, 'locked', status)
                    WHERE 
                        username = :username";
            return $this->execute_non_query($sql, [
                ':username'                 => $username,
                ':unique_login_id'          => $unique_login_id,
                ':failed_attempt'           => $failed_attempt ? 1 : 0,
                ':max_failed_attempt_count' => config::MAX_FAILED_SIGN_IN_COUNT
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function get_recovery_qa(...): array
    /**
     * Gets a record from "recovery_qa" table
     *
     * @param string $username
     * @return array|null returns null if records not found otherwise data records
     * @throws db_exception
     */
    public function get_recovery_qa(string $username): array|null {
        try {
            $sql = "SELECT question,answer FROM recovery_qa WHERE username = :username ORDER BY Id DESC LIMIT 3";
            return $this->execute_query($sql, [':username' => $username]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_RETRIEVING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_personal(...): int
    /**
     * edits a record in users table
     *
     * @param string $username
     * @param string $real_name
     * @param string $mobile_no
     * @param string $email
     * @param string $language
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_personal(string $username, string $real_name, string $mobile_no, string $email, string $language): int {
        try {
            // if you like to keep previous value of column when value of param is empty,
            //     use following sql code IF (:col = '', col, :col),
            $sql = 'UPDATE users SET
                real_name     = :real_name,
                mobile_no     = :mobile_no,
                email         = :email,
                language      = :language,
                modified_by   = :username,
                date_modified = now()
            WHERE
                username      = :username';
            return $this->execute_non_query($sql, [
                ':real_name' => mb_strimwidth($real_name, 0, 50),
                ':mobile_no' => mb_strimwidth($mobile_no, 0, 16),
                ':email'     => mb_strimwidth($email, 0, 50),
                ':language'  => mb_strimwidth($language, 0, 2),
                ':username'  => $username
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_status(...): int
    /**
     * edits a record in users table
     *
     * @param string $username
     * @param string $status can be 'initial','verified','approved','active','dormant','banned','deleted'
     * @param string $modified_by
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_status(string $username, string $status, string $modified_by): int {
        try {
            $sql = 'UPDATE users SET
                status        = :status,
                modified_by   = :modified_by,
                date_modified = now()
            WHERE
                username      = :username';
            return $this->execute_non_query($sql, [
                ':status'      => $status,
                ':modified_by' => $modified_by,
                ':username'    => $username
            ]);
        } catch (Exception $ex) {
            // 01000: invalid enum data
            if ($ex instanceof \PDOException && $ex->getCode() == '01000')
                return 0;
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_photo(...): int
    /**
     * @param string $username
     * @param string $photo_data
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_photo(string $username, string $photo_data): int {

        try {
            $uuid = user_photos::save_from_b64_image_data($photo_data);
            if ($uuid == 0)
                return 0;
            $sql = 'UPDATE users SET
                        photo         = :photo,
                        modified_by   = :modified_by,
                        date_modified = now()
                    WHERE
                        username = :username';
            return $this->execute_non_query($sql, [
                ':username' => $username,
                ':photo'    => $uuid
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_security_image(...): int
    /**
     * @param string $username
     * @param string $security_image
     * @param string $security_phrase
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_security_image(string $username, string $security_image, string $security_phrase): int {
        try {
            $sql = "UPDATE users SET 
                security_image    = :security_image,
                security_phrase   = :security_phrase,
                date_modified = now()
            WHERE 
                username      = :username";
            return $this->execute_non_query($sql, [
                ':username'        => $username,
                ':security_image'  => mb_strimwidth($security_image, 0, 36),
                ':security_phrase' => mb_strimwidth($security_phrase, 0, 32),
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_password(...): int
    /**
     * @param string $username
     * @param string $password
     * @param string $password_salt
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_password(string $username, string $password, string $password_salt): int {
        try {
            $sql = "UPDATE users SET 
                password         = :password,
                password_salt    = :password_salt,
                date_pwd_changed = now()
            WHERE 
                username = :username";
            return $this->execute_non_query($sql, [
                ':username'      => $username,
                ':password'      => $password,
                ':password_salt' => $password_salt
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_explicit_permissions(...): int
    /**
     * @param string $username
     * @param string $permissions
     * @param string $modified_by
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_explicit_permissions(string $username, string $permissions, string $modified_by): int {
        try {
            $sql = "UPDATE users SET 
                permissions   = :permissions,
                modified_by   = :modified_by,
                date_modified = now()
            WHERE 
                username             = :username";
            return $this->execute_non_query($sql, [
                ':username'    => $username,
                ':permissions' => mb_strimwidth($permissions, 0, 4000),
                ':modified_by' => $modified_by
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_dormant(...): array
    /**
     * Set inactive accounts to dormant
     *
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_dormant(): int {
        try {
            $sql = "UPDATE users SET
                    status = 'dormant',
                    date_locked_out = NOW()
                 WHERE 
                    (
                        (date_signed_in is not null AND DATE_ADD(date_signed_in, INTERVAL :dormant_period DAY)  < NOW()) OR
                        (date_signed_in is null AND DATE_ADD(date_created, INTERVAL :dormant_after_created DAY)  < NOW())   
                    ) AND
                    roles NOT LIKE :super_admin_role AND
                    status <> 'dormant'
                ";
            return $this->execute_non_query($sql, [
                ':super_admin_role'      => '%;' . config::SUPER_ADMIN_ROLE . ';%',
                ':dormant_period'        => config::DORMANT_PERIOD,
                ':dormant_after_created' => config::DORMANT_AFTER_CREATED
            ]);
        } catch (Exception $ex) {
            throw new db_exception('##ERROR_MODIFYING_RECORDS_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

    #region public function set_recovery_qa(...): int;
    /**
     * set QA Recovery
     *
     * @param string $username
     * @param string $question
     * @param string $answer
     * @return int count of affected rows
     * @throws db_exception
     */
    public function set_recovery_qa(string $username, array $qa): int {
        try {
            $sql = "INSERT INTO recovery_qa(
                username, question, answer
            ) VALUES 
                  (:username, :question1, :answer1),
                  (:username, :question2, :answer2),
                  (:username, :question3, :answer3)";
            return $this->execute_non_query($sql, [
                ':username'  => $username,
                ':question1' => array_keys($qa)[0],
                ':answer1'   => array_values($qa)[0],
                ':question2' => array_keys($qa)[1],
                ':answer2'   => array_values($qa)[1],
                ':question3' => array_keys($qa)[2],
                ':answer3'   => array_values($qa)[2],
            ]);
        } catch (\Exception $ex) {
            throw new db_exception('##ERROR_ADDING_RECORD_FAILED##', intval($ex->getCode()), $ex);
        }
    }
    #endregion

}
