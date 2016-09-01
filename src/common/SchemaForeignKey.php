<?php

namespace Motia\Generator\Common;

class SchemaForeignKey
{
    /** @var  string */
    public $model;
    public $refModel;
    public $localKey;
    public $otherKey;
    public $table;
    public $refTable;

    /** @var array */
    public $fieldSettings = [];
    public $errors = [];
    /** @var  ForeignKeyMap */
    public $foreignKeyMap;
    /** @var  string */
    private $onUpdate;
    private $onDelete;


    /**
     * SchemaForeignKey constructor.
     * @param $foreignKeyMap
     */
    public function __construct($foreignKeyMap = null)
    {
        $this->foreignKeyMap = $foreignKeyMap;
    }

    public function parseForeignKey($keySettings, $fieldSettings = [], $overrideFieldSettings = false)
    {
        if (isset($keySettings)) {
            foreach ($keySettings as $key => $value) {
                if ($this->checkForInconsistency($key, $value, 'from-relation')) {
                    $this->$key = $value;
                }
            }
        }

        $dbInput = array_pull($fieldSettings, 'dbType');

        // checking for table name and primary key consistency
        // perhaps I can add checking for foreign key type consistency
        if (isset($dbInput)) {
            $dbInputs = explode(',', $dbInput);
            foreach ($dbInputs as $input) {
                if (str_contains($input, 'foreign')) {
                    $tokens = explode(',', $input);
                    $length = count($tokens);
                    if ($length > 1) {
                        $this->checkForInconsistency('refTable', $tokens[1], 'from-settings');
                        $this->refTable = $tokens[1];
                    }
                    if ($length > 2) {
                        $this->checkForInconsistency('otherKey', $tokens[2], 'from-settings');
                        $this->refTable = $tokens[2];
                    }
                }
            }
        }

        if ($overrideFieldSettings) {
            $this->fieldSettings = array_merge($this->fieldSettings, $fieldSettings);
        } else {
            $this->fieldSettings = array_merge($fieldSettings, $this->fieldSettings);
        }
    }

    private function checkForInconsistency($member, $value, $context)
    {
        $error = isset($this->$member) && $this->$member != ModelSchema::$wildCard && $this->$member != $value;
        if ($error) {
            $this->errors[] = [
                'context' => 'foreign-key-' . $context,
                'member' => $member,
                'old' => $this->$member,
                'new' => $value
            ];
        }
        return !$error;
    }

    public function fillPlaceHolders()
    {
        // todo
    }

    public function getFieldRepresentation()
    {
        // todo
    }
}
