<?php

namespace Motia\Generator\Utils;

use Motia\Generator\Utils\GeneratorRelationshipsUtilInterface;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratorHasOneRelationshipUtil implements GeneratorRelationshipsUtilInterface
{
    use GeneratorRelationshipInputUtilTrait;

    public static function validateRelationship($relationshipInput)
    {
        // TODO: Implement validateRelationship() method.
    }

    public static function processRelationshipInput($relationshipInput, $htmlType, $validations, $fieldSettings = [])
    {
        // TODO: Implement processRelationship() method.
    }

    public static function validateRelationshipInput($relationInput)
    {
        // TODO: Implement validateRelationshipInput() method.
    }
}
