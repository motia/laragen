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

    public static function getForeignKeyColumns($pulledRelationships)
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

        $fkOptionsFromFieldInput['type'] = array_shift($fieldInputs);
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

        return [
            'fieldInput'  => $fieldInput,
            'fkOptions'   => $fkOptions,
            'htmlType'    => isset($foreignKeyField['htmlType']) ? $foreignKeyField['htmlType'] : 'text',
            'validations' => isset($foreignKeyField['validations']) ? $foreignKeyField['validations'] : '',
            'searchable'  => isset($foreignKeyField['searchable']) ? $foreignKeyField['searchable'] : true,
            'fillable'    => isset($foreignKeyField['fillable']) ? $foreignKeyField['fillable'] : true,
            'primary'     => isset($foreignKeyField['primary']) ? $foreignKeyField['primary'] : false,
            'inForm'      => isset($foreignKeyField['inForm']) ? $foreignKeyField['inForm'] : true,
            'inIndex'     => isset($foreignKeyField['inForm']) ? $foreignKeyField['inForm'] : true,
        ];
    }

    private static function preparePivotTableName($table1, $table2)
    {
        $first = Str::singular(min($table1, $table2));
        $second = Str::singular(max($table1, $table2));
        // fixme name is not on the laravel standard to match the laravel-generator package
        return Str::plural(Str::lower($first.'_'.$second));
    }

    /**
     * @param string $table
     *
     * @return string
     */
    private static function generateModelNameFromTableName($table)
    {
        return ucfirst(camel_case(str_singular($table)));
    }

    /**
     * @param $relationshipSettings
     * @param $relationshipType
     * @param $relatedModel
     * @param $relatedTable
     * @param $modelName
     * @param $tableName
     * @param $relationshipName
     * @return array
     */
    private static function prepareForeignKeys($relationshipSettings, $relationshipType, $relatedModel, $relatedTable, $modelName, $tableName, $relationshipName)
    {
        $fkFields = [];
        if ($relationshipType == 'hasOne' || $relationshipType == 'hasMany') {
            $fkField = isset($relationshipSettings['fkFields'][0]) ?
                $relationshipSettings['fkFields'][0] : [];

            $fkFields[] = self::validateForeignKeyField($fkField, $relatedModel, $relatedTable, $modelName, $tableName, $relationshipName);
        } elseif ($relationshipType == 'belongsTo') {
            $fkField = isset($relationshipSettings['fkFields'][0]) ?
                $relationshipSettings['fkFields'][0] : [];

            $fkFields[] = self::validateForeignKeyField($fkField, $modelName, $tableName, $relatedModel, $relatedTable, $relationshipName);
        } else { // belongsToMany
            $fkField1 = isset($relationshipSettings['fkFields'][0]) ? $relationshipSettings['fkFields'][0] : [];
            $fkField2 = isset($relationshipSettings['fkFields'][1]) ? $relationshipSettings['fkFields'][1] : [];

            // TODO support custom pivot table names
            $pivotTable = self::preparePivotTableName($modelName, $relatedModel);
            $pivotModel = self::generateModelNameFromTableName($pivotTable);

            $fkFields[] = self::validateForeignKeyField($fkField1, $pivotModel, $pivotTable, $modelName, $tableName, $relationshipName);
            $fkFields[] = self::validateForeignKeyField($fkField2, $pivotModel, $pivotTable, $relatedModel, $relatedTable, $relationshipName);
        }
        return $fkFields;
    }
}
