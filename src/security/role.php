<?php

namespace security;

use exceptions\db_exception;

class role {

    #region Properties
    /**
     * [PK] varchar(32), utf8mb3_general_ci
     *
     * @var string Name of the role
     */
    public string $name;

    /**
     * [MUL] int(11)
     * default 0
     *
     * @var int organization id
     */
    public int $organization_id;

    /**
     * varchar(100), utf8mb3_general_ci
     *
     * @var string Description of role
     */
    public string $description;

    /**
     * text, utf8mb3_general_ci
     *
     * @var array Permissions of this role
     */
    public array $permissions;

    /**
     * tinyint(1)
     * default 0
     *
     * @var bool Is this role visible to organization subtree below
     */
    public bool $visible_below;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string Use who has created this role
     */
    public string $created_by;

    /**
     * datetime
     * default current_timestamp()
     *
     * @var string|null Date this role was created
     */
    public string|null $date_created;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string Last user who has modified the record
     */
    public string $modified_by;

    /**
     * datetime
     *
     * @var string|null Date this record was last modified
     */
    public string|null $date_modified;
    #endregion

    #region public function __construct(...)
    /**
     * role Constructor
     */
    public function __construct() {
        $this->name = '';
        $this->organization_id = 0;
        $this->description = '';
        $this->visible_below = true;
        $this->permissions = [];
        $this->created_by = '';
        $this->date_created = null;
        $this->modified_by = '';
        $this->date_modified = null;
    }
    #endregion

    #region public static function guests(): Role
    /**
     * Returns a Guest Role
     *
     * @return role guest Role
     */
    public static function guests(): role {
        $role = new role();
        $role->name = 'guests';
        $role->organization_id = -1;
        $role->visible_below = 1;
        $role->description = '##GUESTS##';
        return $role;
    }
    #endregion

    #region public static function root_admin(): Role
    /**
     * Returns a root admin role
     *
     * @return role root admin Role
     */
    public static function root_admin(): role {
        $role = new role();
        $role->name = 'root_administrators';
        $role->organization_id = 0;
        $role->visible_below = 0;
        $role->description = '##ROOT_ADMINISTRATORS##';
        return $role;
    }
    #endregion

    #region public function from_array(...)
    /**
     * Used to convert returned record array from PDO to (role) class
     * @param array $record Array containing role properties
     * @throws db_exception
     */
    public static function from_array(array $record): role {
        if ($record == []) {
            throw new db_exception('##ERROR_ARRAY_IS_EMPTY##');
        }
        if (!key_exists('name', $record) || $record['name'] == '') {
            throw new db_exception('##ERROR_ARRAY_DOES_NOT_HAVE_NAME_KEY##');
        }

        $role = new role();
        $role->name = $record['name'] ?? $role->name;
        $role->organization_id = $record['organization_id'] ?? 0;
        $role->description = $record['description'] ?? $role->description;
        $role->permissions = isset($record['permissions']) ? explode(';', trim($record['permissions'], ';')) : $role->permissions;
        $role->visible_below = boolval($record['visible_below'] ?? 0);
        $role->created_by = $record['created_by'] ?? $role->created_by;
        $role->date_created = $record['date_created'] ?? $role->date_created;
        $role->modified_by = $record['modified_by'] ?? $role->modified_by;
        $role->date_modified = $record['date_modified'] ?? $role->date_modified;
        return $role;
    }
    #endregion

    #region public function as_array(...): array
    /**
     * @return array role information as array
     */
    public function as_array(): array {
        return [
            'name'            => $this->name,
            'organization_id' => $this->organization_id,
            'description'     => $this->description,
            'visible_below'   => $this->visible_below,
            'permissions'     => implode(';', $this->permissions),
            'created_by'      => $this->created_by,
            'date_created'    => $this->date_created,
            'modified_by'     => $this->modified_by,
            'date_modified'   => $this->date_modified,
        ];
    }
    #endregion

}
