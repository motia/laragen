<?php

namespace Motia\Generator\Utils;

use Illuminate\Support\Str;

trait GeneratorRelationshipInputUtilTrait
{
    public static function prepareKeyValueArrayStr($arr)
    {
        $arrStr = '[';
        foreach ($arr as $item) {
            $arrStr .= "'$item' => '$item', ";
        }

        $arrStr = substr($arrStr, 0, strlen($arrStr) - 2);

        $arrStr .= ']';

        return $arrStr;
    }

    public static function prepareValuesArrayStr($arr)
    {
        $arrStr = '[';
        foreach ($arr as $item) {
            $arrStr .= "'$item', ";
        }

        $arrStr = substr($arrStr, 0, strlen($arrStr) - 2);

        $arrStr .= ']';

        return $arrStr;
    }

    public static function pullRelationships(&$jsonData, $modelName = '')
    {
        $modelName .= ':';
        $pulledRelationships = [];
        foreach ($jsonData as $index => $field) {
            if (isset($field['relationshipInput'])) {
                $field['relationshipInput'] = $modelName.$field['relationshipInput'];

                $pulledRelationship = self::validateRelationship($field);
                $relationshipName = $pulledRelationship['relationshipName'];
                $pulledRelationships[$relationshipName] = $pulledRelationship;

                unset($jsonData[$index]);
            }
        }

        return $pulledRelationships;
    }

    public static function getForeignKeysColumn($pulledRelationships)
    {
        $result = [];
        foreach ($pulledRelationships as $relationship) {
            $result += $relationship['fkFields'];
        }

        return $result;
    }

    public static function validateForeignKeyField($foreignKeyField, $model, $table, $referencedModel, $referencedTable, $relationshipName)
    {
        $fkOptions = isset($foreignKeyField['fkOptions']) ? $foreignKeyField['fkOptions'] : [];
        $fkOptions['model'] = $model;
        $fkOptions['table'] = $table;
        $fkOptions['referencedModel'] = $referencedModel;
        $fkOptions['referencedTable'] = &$fkOptions['on']; // HACK
        $fkOptions['relationshipName'] = $relationshipName;

        $defaultFKOptions = [
            'type'       => 'integer,false,true',
            'references' => 'id',
            'on'         => $referencedTable,
            'onUpdate'   => 'RESTRICT',
            'onDelete'   => 'RESTRICT',
        ];

        $fieldInputs = [];
        if (isset($foreignKeyField['fieldInput'])) {
            $fieldInputs = explode(':', $foreignKeyField['fieldInput']);
        }

        $fkOptionsFromFieldInput = [];
        $otherDatabaseInputs = [];

        $fkOptionsFromFieldInput['type']  = array_shift($fieldInputs);
        $fkOptionsFromFieldInput['field'] = array_shift($fieldInputs);

        foreach ($fieldInputs as $index => $fieldInput) {

            $tokens = explode(',', $fieldInput);

            if (count($tokens) == 1) {
                $otherDatabaseInputs[] = $tokens[0];
            } else {
                $token = array_shift($tokens);
                $fkOptionsFromFieldInput[$token] = implode(',', $tokens);
            }
        }

        if (!isset($fkOptions['references'])) {
            if (isset($fkOptionsFromFieldInput['references'])) {
                $fkOptions['references'] = $fkOptionsFromFieldInput['references'];
            }
            if (!isset($fkOptions['references']) || $fkOptions['references'] == '$') {
                $fkOptions['references'] = $defaultFKOptions['references'];
            }
        }

        $defaultFKOptions['field'] = Str::snake($referencedModel).'_'.$fkOptions['references'];

        foreach (['field', 'on', 'onUpdate', 'onDelete', 'type'] as $option) {
            if (!isset($foreignKeyField[$option])) {
                if (isset($fkOptionsFromFieldInput[$option])) {
                    $fkOptions[$option] = $fkOptionsFromFieldInput[$option];
                }
                if (!isset($foreignKeyField[$option]) || $foreignKeyField[$option] == '$') {
                    $fkOptions[$option] = $defaultFKOptions[$option];
                }
            }
        }

        $fieldInput = $fkOptions['field']
            .':'.$fkOptions['type']
            .implode(',', $otherDatabaseInputs);

        $result = compact('fieldInput', 'fkOptions');
        /*
            'searchable' => $foreignKeyField['searchable'],
            'fillable'   => $foreignKeyField['fillable'],
            'primary'    => $foreignKeyField['primary'],
            'inForm'     => $foreignKeyField['inForm'],
            'inIndex'    => $foreignKeyField['index'],
        ];

        //TODO
        $htmlType = $foreignKeyField['htmlType'];
        $validations = $foreignKeyField['validations'];
        */
        return $result;
    }
}
