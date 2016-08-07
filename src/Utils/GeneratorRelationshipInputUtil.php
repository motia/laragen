<?php

namespace Motia\Generator\Utils;

use Illuminate\Support\Str;
use RuntimeException;

class GeneratorRelationshipInputUtil
{
    use GeneratorRelationshipInputUtilTrait;

    public static $AVAILABLE_RELATIONSHIPS = ['belongsTo', 'hasOne', 'belongsToMany', 'hasMany'];

    /**
     * @param $relationship
     *
     * @return array|null|string
     */
    public static function validateRelationship($relationship)
    {
        $relationType = self::validateRelationshipInput($relationship['relationshipInput']);
        if (!$relationType) {
            throw new RuntimeException('Invalid Input '.$relationship['relationshipInput']);
        }
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

    public static function validateRelationshipInput($relationshipInput)
    {
        $relationshipInputs = explode(':', $relationshipInput);

        if (count($relationshipInputs) < 2) {
            return false;
        }

        $requiredRelationshipInputs = explode(',', $relationshipInputs[1]);
        $relationshipType = $requiredRelationshipInputs[0];

        return (in_array($relationshipType, self::$AVAILABLE_RELATIONSHIPS)) ? $relationshipType : false;
    }

    public static function processRelationshipInput($relationshipInput,
                                                    $htmlType,
                                                    $validations,
                                                    $relationshipSettings = [])
    {
        $relationshipInputs = explode(':', $relationshipInput);

        $modelName = array_shift($relationshipInputs);
        $tableName = Str::snake(Str::plural($modelName));

        $requiredRelationshipInput = $relationshipInputs[0];
        $requiredRelationshipInputs = explode(',', $requiredRelationshipInput);

        $relationshipType = array_shift($requiredRelationshipInputs);
        $relatedModel = array_shift($requiredRelationshipInputs);
        $relatedTable = Str::snake(Str::plural($relatedModel)); // fixme relatedTable not always deduced that way
        $relationshipName = (str_contains($relationshipType, 'Many')) ?  Str::plural($relatedModel) : $relatedModel;

        $fkFields = self::prepareForeignKeys($relationshipSettings, $relationshipType, $relatedModel, $relatedTable, $modelName, $tableName, $relationshipName);

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
            'fkFields'           => $fkFields,
        ];
    }

}
