<?php

namespace Motia\Generator\Utils;

interface GeneratorRelationshipInputUtilInterface
{
    public static function validateRelationship($relationshipInput);

    public static function processRelationshipInput($relationshipInput, $htmlType, $validations, $fieldSettings = []);

    public static function validateRelationshipInput($relationInput);

    public static function pullRelationships(&$jsonData);

    public static function getForeignKeysColumn($pulledRelationships);
}
