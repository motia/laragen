<?php

namespace Motia\Generator\Utils;

use RuntimeException;

class GeneratorRelationshipInputUtil implements GeneratorRelationshipInputUtilInterface
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
        switch ($relationType) {
            case 'belongsTo':
                return GeneratorBelongsToRelationshipUtil::validateRelationship($relationship);
            case 'hasOne':
                return GeneratorHasOneRelationshipUtil::validateRelationship($relationship);
            case 'belongsToMany':
                return GeneratorBelongsToManyRelationshipUtil::validateRelationship($relationship);
            case 'hasMany':
                return GeneratorHasManyRelationshipUtil::validateRelationship($relationship);
        }
    }

    public static function processRelationshipInput($relationshipInput,
                                                         $htmlType,
                                                         $validations,
                                                         $fieldSettings = [])
    {
        $relationType = self::validateRelationshipInput($relationshipInput);

        if (!$relationType) {
            throw new RuntimeException('Invalid Input '.$relationshipInput);
        }

        switch ($relationType) {
            case 'belongsTo':
                return GeneratorBelongsToRelationshipUtil::processRelationshipInput($relationshipInput);
            case 'HasOne':
                return GeneratorHasOneRelationshipUtil::processRelationshipInput($relationshipInput);
            case 'belongsToMany':
                return GeneratorBelongsToManyRelationshipUtil::processRelationshipInput($relationshipInput);
            case 'hasMany':
                return GeneratorHasManyRelationshipUtil::processRelationshipInput($relationshipInput);
        }
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
}
