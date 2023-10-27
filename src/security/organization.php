<?php

namespace security;

use exceptions\db_exception;

class organization {

    #region Properties
    /**
     * [PK] int(11)
     */
    public int|null $id;

    /**
     * [MUL] int(11)
     *
     * @var int|null Parent node in organization tree
     */
    public int|null $parent_id;

    /**
     * [MUL] varchar(32), utf8mb3_general_ci
     *
     * @var string organization name
     */
    public string $name;

    /**
     * varchar(100), utf8mb3_general_ci
     *
     * @var string organization display name
     */
    public string $description;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string user who created this node
     */
    public string $created_by;

    /**
     * datetime
     * default current_timestamp()
     *
     * @var string|null date that this record was created
     */
    public string|null $date_created;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string last user who modified this node
     */
    public string $modified_by;

    /**
     * datetime
     *
     * @var string|null last date that this record was modified
     */
    public string|null $date_modified;
    #endregion

    #region public function __construct(...)
    public function __construct() {
        $this->id = -1;
        $this->parent_id = null;
        $this->name = '';
        $this->description = '';
        $this->created_by = '';
        $this->date_created = '';
        $this->modified_by = '';
        $this->date_modified = '';
    }
    #endregion

    #region public function from_array(...)
    /**
     * Used to convert returned record array from PDO to (organization) class
     * @param array $record Array containing organization properties
     * @throws db_exception
     */
    public static function from_array(array $record): organization {
        if ($record == []) {
            throw new db_exception('##ERROR_ARRAY_IS_EMPTY##');
        }
        if (!key_exists('id', $record) || $record['id'] == '') {
            throw new db_exception('##ERROR_ARRAY_DOES_NOT_HAVE_NAME_KEY##');
        }

        $organization = new organization();
        $organization->id = $record['id'] ?? $organization->id;
        $organization->parent_id = $record['parent_id'] ?? $organization->parent_id;
        $organization->name = $record['name'] ?? $organization->name;
        $organization->description = $record['description'] ?? $organization->description;
        $organization->created_by = $record['created_by'] ?? $organization->created_by;
        $organization->date_created = $record['date_created'] ?? $organization->date_created;
        $organization->modified_by = $record['modified_by'] ?? $organization->modified_by;
        $organization->date_modified = $record['date_modified'] ?? $organization->date_modified;
        return $organization;
    }
    #endregion

    #region public function as_array(...): array
    /**
     * @return array organization information as array
     */
    public function as_array(): array {
        return [
            'id'            => $this->id,
            'parent_id'     => $this->parent_id,
            'name'          => $this->name,
            'description'   => $this->description,
            'created_by'    => $this->created_by,
            'date_created'  => $this->date_created,
            'modified_by'   => $this->modified_by,
            'date_modified' => $this->date_modified
        ];
    }
    #endregion

}
