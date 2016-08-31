<?php

namespace Motia\Generator\Common;


class ForeignKeyMap
{
    private $map = [];

    public function getMap()
    {
        return $this->map;
    }

    public function store($model, $foreignKey)
    {
        $this->map[$model][] = $foreignKey;
    }

    public function registerModel($model){
        $this->map[$model] = [];
    }

    public function hasModel($model)
    {
        return key_exists($model, $this->map);
    }

    /**
     * @param $model
     * @param $refModel
     * @return SchemaForeignKey|null
     */
    public function getForeignKey($model, $refModel)
    {
        $foreignKeys = $this->map[$model];
        /** @var SchemaForeignKey[] $foreignKeys */
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->refModel == $refModel) {
                return $foreignKey;
            }
        }
        return null;
    }


    public function updateOrStoreForeignKey($fkSettings, $fieldSettings, $overrideFieldSettings = false)
    {
        $model = $fkSettings['model'];
        $refModel = $fkSettings['refModel'];
        $foreignKeys = $this->map[$model];
        /** @var SchemaForeignKey[] $foreignKeys */
        foreach ($foreignKeys as $fk) {
            if ($fk->refModel == $refModel) {
                $fk->parseForeignKey($fkSettings, $fieldSettings, $overrideFieldSettings);
                return true;
            }
        }
        $fk = new SchemaForeignKey();
        $fk->parseForeignKey($fkSettings, $fieldSettings, $overrideFieldSettings);

        $this->map[$model][] = $fk;

        return false;
    }
}