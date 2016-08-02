<?php

namespace Motia\Generator\Utils;

use RuntimeException;
use Motia\Generator\Utils\GeneratorRelationshipInputUtilInterface;

class GeneratorRelationshipInputUtil implements GeneratorRelationshipInputUtilInterface
{
    use GeneratorRelationshipInputUtilTrait;

    public static $AVAILABLE_RELATIONSHIPS = ['belongsTo', 'HasOne', 'belongsToMany', 'hasMany'];

    /**
     * @param $relationship
     * @return array|null|string
     */
    public static function validateRelationship($relationship){
        $relationType = self::validateRelationshipInput($relationship['relationshipInput']);
        if (!$relationType) {
            throw new RuntimeException('Invalid Input '.$relationship['relationship']);
        }
        switch ($relationType){
            case 'belongsTo':
                return GeneratorBelongsToRelationshipUtil::validateRelationship($relationship);
            case 'HasOne':
                return GeneratorHasOneRelationshipUtil::validateRelationship($relationship);
            case 'belongsToMany':
                return GeneratorBelongsToManyRelationshipUtil::validateRelationship($relationship);
            case 'hasMany':
                return GeneratorHasManyRelationshipUtil::validateRelationship($relationship);
        }
        return null;
    }

    public static function processRelationshipInput($relationshipInput,
                                                         $htmlType,
                                                         $validations,
                                                         $fieldSettings = []){

        $relationType = self::validateRelationshipInput($relationshipInput);

        if (!$relationType) {
            throw new RuntimeException('Invalid Input '.$relationshipInput);
        }

        switch ($relationType){
            case 'belongsTo':
                return GeneratorBelongsToRelationshipUtil::processRelationshipInput($relationshipInput);
            case 'HasOne':
                return GeneratorHasOneRelationshipUtil::processRelationshipInput($relationshipInput);
            case 'belongsToMany':
                return GeneratorBelongsToManyRelationshipUtil::processRelationshipInput($relationshipInput);
            case 'hasMany':
                return GeneratorHasManyRelationshipUtil::processRelationshipInput($relationshipInput);
        }
        return null;
    }

    public static function validateRelationshipInput($relation)
    {
        $fieldInputs = explode(':', $relation);

        if (count($fieldInputs) < 2 || !in_array($fieldInputs[0], self::$AVAILABLE_RELATIONSHIPS)) {
            return false;
        }
        return $fieldInputs[0];
    }


}