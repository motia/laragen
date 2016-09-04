<?php

namespace Motia\Generator\Common;

class SchemaForeignKey
{
    /** @var  ForeignKeyMap */
    public $foreignKeyMap;

    public $defaulted;
    /** @var  string */
    public $model;
    public $refModel;
    public $refTable;
    public $localKey;
    public $otherKey;

    /** @var  string */
    private $onUpdate;
    private $onDelete;

    public function __construct()
    {
        $this->defaulted = array_fill_keys(['localKey', 'otherKey', 'refTable', 'onUpdate', 'onDelete'], true);
    }

    public function parseForeignKey($keySettings, $fieldSettings = [])
    {
        if (isset($keySettings)) {
            $this->model = array_pull($keySettings, 'model');
            $this->refModel = array_pull($keySettings, 'refModel');

            foreach ($keySettings as $key => $value) {
                $this->updateDefaulted($key, $value);
            }
        }
        $dbInput = array_pull($fieldSettings, 'dbType');
        if (isset($dbInput)) {
            $dbInputs = explode(':', $dbInput);
            foreach ($dbInputs as $input) {
                $option = explode(':', $input)[0];
                if ($option == 'foreign') {
                    $tokens = explode(',', $input);
                    $this->updateDefaulted('refTable', $tokens[1]);
                    $this->updateDefaulted('otherKey', $tokens[2]);
                } elseif ($option == 'onUpdate') {
                    $this->onUpdate = substr($input, strlen('onUpdate:'));
                } elseif ($option == 'onDelete') {
                    $this->onDelete = substr($input, strlen('onDelete:'));
                }
            }
        }

        if($this->defaulted['localKey']) {
            $this->localKey = snake_case($this->localKey) . '_' . 'id';
        }
        if($this->defaulted['otherKey']) {
            $this->otherKey = 'id';
        }

    }

    private function updateDefaulted($key, $value)
    {
        if ($this->isUpdatable($key, $value)) {
            $this->$key = $value;
            $this->defaulted[$key] = false;
        } else {
            // notify for inconsistency
        }
    }

    private function isUpdatable($key, $value)
    {
        return $this->defaulted[$key] && $value != ModelSchema::WILD_CARD;
    }

    public function fillPlaceHolders()
    {
        // todo
    }

    public function getFieldRepresentation(array $field = [], $separated = false)
    {
        // todo verify for consistency
        if (isset($field['name'])) {
            $this->updateDefaulted('localKey', $field['name']);
        }

        /** @var ModelSchema $refSchema */
        $refSchema = $this->foreignKeyMap->command->schemas[$this->refModel];
        $refTable = ($this->defaulted['refTable']) ? $refSchema->tableName : $this->refTable;
        $primary = ($this->defaulted['otherKey']) ? $refSchema->getPrimaryKey() : $this->otherKey;

        if ($this->defaulted['localKey']) {
            $this->localKey = snake_case($this->refModel) . '_' . $primary;
        }

        $type = 'unsigned';
        if (isset($field['dbType'])) {
            $type = $field['dbType'];
        } else {
            if (isset($refSchema->fields[$primary])) {
                $type = $refSchema->fields[$primary];
                if ($type == 'increments' || $type == 'integer,true,true')
                    $type = 'unsigned';
            }
        }

        $result = [
            'name' => $this->localKey,
            'dbType' => $type,
        ];
        if ($separated) {
            $result['foreignKey'] = [
                'field' => $this->localKey,
                'references' => $primary,
                'on' => $refTable,
                'onUpdate' => $this->onUpdate,
                'onDelete' => $this->onDelete,
            ];
        } else
            $result['dbType'] .= ':' . implode(',', ['foreign', $refTable, $primary]);

        return $result;
    }
}
