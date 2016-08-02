<?php

namespace Motia\Generator\Utils;

use Illuminate\Support\Str;
use InfyOm\Generator\Utils\GeneratorFieldsInputUtil;
use Motia\Generator\Utils\GeneratorRelationshipInputUtilInterface;

use RuntimeException;

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
            'fkFieldInputs' => [],
        ];

        foreach ($relationship['foreignKeys'] as $foreignKeyFieldInput) {
            $relationshipSettings['fkFieldInputs'] = GeneratorFieldsInputUtil::processFieldInput(
                self::replaceByEmptyIfNull($foreignKeyFieldInput['fieldInput']),
                self::replaceByEmptyIfNull($foreignKeyFieldInput['htmlType']),
                self::replaceByEmptyIfNull($foreignKeyFieldInput['validations']),
                [
                    'searchable' => self::replaceByEmptyIfNull($foreignKeyFieldInput['searchable']),
                    'fillable'   => self::replaceByEmptyIfNull($foreignKeyFieldInput['fillable']),
                    'inForm'     => self::replaceByEmptyIfNull($foreignKeyFieldInput['inForm']),
                    'inIndex'    => self::replaceByEmptyIfNull($foreignKeyFieldInput['inIndex']),
                ]
            );
        }

        return
            self::processRelationshipInput($relationship['fieldInput'], $htmlType, $validations, $relationshipSettings);
    }

    public static function processRelationshipInput($relationshipInput,
                                                    $htmlType,
                                                    $validations,
                                                    $relationshipSettings = []){
        $relationshipInputs = explode(':', $relationshipInput);

        $relationshipName = array_shift($relationshipInputs);
        $eloquentInputs = implode(':', $relationshipInputs);
        $relationshipType = explode(',', $relationshipInputs[0])[0];

        $htmlTypeInputs = explode(':', $htmlType);
        $htmlType = array_shift($htmlTypeInputs);

        if (count($htmlTypeInputs) > 0) {
            $htmlTypeInputs = array_shift($htmlTypeInputs);
        }

        return [
            'relationshipInput'     => $relationshipInput,
            'relationshipTitle'     => Str::title(str_replace('_', ' ', $relationshipName)),
            'relationshipType'      => $relationshipType,
            'relationshipName'      => $relationshipName,
            'eloquentInputs' => $eloquentInputs,
            'htmlType'       => $htmlType,
            'htmlTypeInputs' => $htmlTypeInputs,
            'validations'    => $validations,
            'searchable'     => isset($relationshipSettings['searchable']) ? $relationshipSettings['searchable'] : false,
            'fillable'       => isset($relationshipSettings['fillable']) ? $relationshipSettings['fillable'] : true,
            'primary'        => isset($relationshipSettings['primary']) ? $relationshipSettings['primary'] : false,
            'inForm'         => isset($relationshipSettings['inForm']) ? $relationshipSettings['inForm'] : true,
            'inIndex'        => isset($relationshipSettings['inIndex']) ? $relationshipSettings['inIndex'] : true,
            'fkFieldInputs'  => $relationshipSettings['$fkFieldInputs'],
        ];
    }

    public static function validateRelationshipInput($relationInput)
    {
        // TODO: Implement validateRelationshipInput() method.
    }
}
