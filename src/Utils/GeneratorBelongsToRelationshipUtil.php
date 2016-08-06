<?php

namespace Motia\Generator\Utils;

use Illuminate\Support\Str;

class GeneratorBelongsToRelationshipUtil implements GeneratorRelationshipInputUtilInterface
{
    use GeneratorRelationshipInputUtilTrait;

    public static function validateRelationship($relationship)
    {
        if (isset($relationship['htmlType'])) {
            $htmlType = $relationship['htmlType'];
        } else {
            $htmlType = 'text';
        }

        if (isset($relationship['validations'])) {
            $validations = $relationship['validations'];
        } else {
            $validations = '';
        }

        if (isset($relationship['searchable'])) {
            $searchable = $relationship['searchable'];
        } else {
            $searchable = false;
        }

        // fillable property is for the related foreign keys
        if (isset($relationship['fillable'])) {
            $fillable = $relationship['fillable'];
        } else {
            $fillable = true;
        }

        if (isset($relationship['inForm'])) {
            $inForm = $relationship['inForm'];
        } else {
            $inForm = true;
        }

        if (isset($relationship['inIndex'])) {
            $inIndex = $relationship['inIndex'];
        } else {
            $inIndex = true;
        }

        $relationshipSettings = [
            'searchable' => $searchable,
            'fillable'   => $fillable,
            'inForm'     => $inForm,
            'inIndex'    => $inIndex,
            'fkFields'   => isset($relationship['fkFields']) ? $relationship['fkFields'] : [],
        ];

        return
            self::processRelationshipInput($relationship['relationshipInput'], $htmlType, $validations, $relationshipSettings);
    }

    public static function validateRelationshipInput($relationInput)
    {
        $fieldInputs = explode(':', $relationInput);

        if (count($fieldInputs) < 2 || $fieldInputs[0] != 'belongsTo') {
            return false;
        }

        return true;
    }

    public static function processRelationshipInput($relationshipInput,
                                                    $htmlType,
                                                    $validations,
                                                    $relationshipSettings = [])
    {
        $relationshipInputs = explode(':', $relationshipInput);

        // modelname ; relationtype,relatedModel,fk1,fk2; eloquentinput1 ; eloquentinput2 ...
        $modelName = array_shift($relationshipInputs);
        $tableName = Str::snake(Str::plural($modelName));

        // relationtype,relatedModel,fk1,fk2 , eloquentinput1 , eloquentinput2 ...
        $requiredRelationshipInput = $relationshipInputs[0];
        $requiredRelationshipInputs = explode(',', $requiredRelationshipInput);

        $relationshipType = array_shift($requiredRelationshipInputs);
        $relatedModel = array_shift($requiredRelationshipInputs);
        $relatedTable = Str::snake(Str::plural($relatedModel));
        $relationshipName = $relatedModel;
        //

        $fkField = isset($relationshipSettings['fkFields'][0]) ?
            $relationshipSettings['fkFields'][0] : [];

        // TODO
        $processedFKField = self::validateForeignKeyField($fkField, $modelName, $tableName, $relatedModel, $relatedTable, $relationshipName);

        $htmlTypeInputs = explode(':', $htmlType);
        $htmlType = array_shift($htmlTypeInputs);

        if (count($htmlTypeInputs) > 0) {
            $htmlTypeInputs = array_shift($htmlTypeInputs);
        }

        return [
            'relationshipInput'  => $relationshipInput,
            'relationshipTitle'  => Str::title(str_replace('_', ' ', $relatedModel)),
            'relationshipType'   => $relationshipType,
            'relationshipName'   => $relationshipName,
            'relationshipInputs' => $relationshipInputs,
            'htmlType'           => $htmlType,
            'htmlTypeInputs'     => $htmlTypeInputs,
            'validations'        => $validations,
            'searchable'         => isset($relationshipSettings['searchable']) ? $relationshipSettings['searchable'] : false,
            'fillable'           => isset($relationshipSettings['fillable']) ? $relationshipSettings['fillable'] : true,
            'primary'            => isset($relationshipSettings['primary']) ? $relationshipSettings['primary'] : false,
            'inForm'             => isset($relationshipSettings['inForm']) ? $relationshipSettings['inForm'] : true,
            'inIndex'            => isset($relationshipSettings['inIndex']) ? $relationshipSettings['inIndex'] : true,
            'fkFields'           => [$processedFKField],
        ];
    }
}
