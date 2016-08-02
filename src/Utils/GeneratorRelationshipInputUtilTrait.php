<?php

namespace Motia\Generator\Utils;

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
    /**
     * @param $jsonData
     * @return mixed
     */
    public static function pullRelationships(&$jsonData)
    {
        $pulledRelationships = [];
        foreach ($jsonData as $index => $field) {
            if (isset($field['relationshipInput'])) {
                $pulledRelationships[] = self::validateRelationship($field);
                unset($jsonData[$index]);
            }
        }
        return $pulledRelationships;
    }

    public static function deduceForeignKeys($pulledRelationships){
        return [];
    }
}