<?php

namespace Motia\Generator\Common;


class ForeignKeyMap
{
    public $command; // todo
    private $map = [];

    public function getMap()
    {
        return $this->map;
    }

    public function registerModel($model){
        if(!key_exists($model, $this->map))
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

    public function updateOrStoreForeignKey($fkSettings, $fieldSettings)
    {
        $model = $fkSettings['model'];
        $refModel = $fkSettings['refModel'];
        $foreignKeys = $this->map[$model];
        /** @var SchemaForeignKey[] $foreignKeys */
        foreach ($foreignKeys as $fk) {
            if ($fk->refModel == $refModel) {
                $fk->parseForeignKey($fkSettings, $fieldSettings);
                return $fk;
            }
        }
        $fk = new SchemaForeignKey($this);
        $fk->parseForeignKey($fkSettings, $fieldSettings);

        $this->map[$model][] = $fk;

        return $fk;
    }
    
    public function getForeignKeys($model){
        return $this->map[$model];
    }
}