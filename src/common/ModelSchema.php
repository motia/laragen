<?php


namespace Motia\Generator\Common;

use Illuminate\Support\Str;
use Symfony\Component\Console\Exception\RuntimeException;


class ModelSchema
{
    const WILD_CARD = '?';
    const SEPARATE_FOREIGN_MIGRATION = true;

    /** @var bool[] */
    /** @var ForeignKeyMap */
    public $foreignKeyMap;
    /** @var  $command */
    public $command;

    /**
     * @var null|string
     */
    public $file;
    public $modelName;
    public $tableName;
    /** @var array */
    public $fields = [];
    public $relationships = [];
    public $createRelationInverse = true;
    /** @var  string */
    protected $primaryKey;

    /**
     * ModelSchema constructor.
     * @param string|null $file
     * @param string|null $modelName
     * @param string|null $table
     */
    public function __construct($file = null, $modelName = null, $table = null)
    {
        if (isset($file)) {
            $this->file = $file;
        }

        if (isset($modelName)) {
            $this->modelName = $modelName;
        } elseif (isset($file)) {
            // take the model_schema file name as the model name
            $baseName = basename($file, '.json');

            $this->modelName = Str::studly(Str::singular($baseName));
        }

        if (isset($table)) {
            $this->tableName = $table;
        } elseif (isset($this->modelName)) {
            $this->tableName = Str::snake(Str::plural($this->modelName));
        }
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @param string $primaryKey
     */
    public function setPrimaryKey($primaryKey)
    {
        if (empty($this->primaryKey) || $this->primaryKey == ModelSchema::WILD_CARD) {
            $this->primaryKey = $primaryKey;
            return;
        }

        if ($primaryKey != $this->primaryKey) {
            throw new RuntimeException("primary key inconsistency for $this->modelName primary key \n
             old value = $this->primaryKey and new value = $primaryKey");
        }
    }

    public function parseDataFromFile()
    {
        if (isset($this->file)) {
            $jsonData = json_decode(file_get_contents($this->file), true);
            if ($jsonData === null) {
                throw new RuntimeException("invalid json for $this->file");
            }
            $this->parseData($jsonData);
        } else {
            throw new RuntimeException("no file name for model/table $this->modelName/$this->tableName");
        }
    }

    /**
     * @param array $jsonData
     */
    public function parseData(array $jsonData)
    {
        if ($this->foreignKeyMap === null) {
            $this->foreignKeyMap = new ForeignKeyMap();
        }

        foreach ($jsonData as $field) {
            if ($fieldPart = $this->getFieldPart($field)) {
                $fieldName = $field['name'];
                $this->fields[$fieldName] = $fieldPart;

            }
            if ($primaryPart = $this->isPrimary($field)) {
                // TODO handle primary key inconsistency exception
                $this->setPrimaryKey($primaryPart['name']);
            }
            if ($relationPart = $this->getRelationPart($field)) {
                $this->parseRelationship($relationPart);
                $this->relationships[] = $relationPart;
                if ($this->createRelationInverse) {
                    $this->appendInverseRelationship($relationPart);
                }
            }
        }
    }

    private static function getFieldPart(array $field)
    {
        dump($field);
        if(isset($field['dbType'])){
            $field['dbType'] = explode(':foreign', $field['dbType'])[0];
        }
        return array_except($field, ['type', 'relation']);
    }

    private static function isPrimary(array $field)
    {
        return isset($field['primary']);
    }

    private static function getRelationPart(array $field)
    {
        return array_only($field, ['type', 'relation', 'dbType']);
    }

    /**
     * @param array $field
     */
    private function parseRelationship(array $field)
    {
        $relation = array_pull($field, 'relation');
        $relationInputs = explode(',', $relation);

        $relationType = array_shift($relationInputs);
        $relatedModel = array_shift($relationInputs);

        if (!isset($field['type']) && ($relationType == '1t1' || $relationType == 'mt1')) {
            // belongsTo relations
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $this->updateForeignKeyFromRelation([
                'model' => $this->modelName,
                'refModel' => $relatedModel,
                'localKey' => $foreignKey,
                'otherKey' => $otherKey,
            ], $field);

        } elseif ($relationType == '1tm' || $relationType == '1t1') {
            // hasOne and hasMany relations
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $this->updateForeignKeyFromRelation([
                'model' => $relatedModel,
                'refModel' => $this->modelName,
                'localKey' => $foreignKey,
                'otherKey' => $otherKey,
            ], $field);
        } elseif ($relationType == 'mtm') {
            // belongsToMany relations

            $pivotTable = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);


            $pivotModel = array_pull($field, 'pivotModel'); // can be null
            if ($pivotModel === null) {
                $this->command->pivots[] = new ModelSchema(null, $pivotModel, $pivotTable);
                $pivotModel = min($this->modelName, $relatedModel) . ' ' . max($this->modelName, $relatedModel);
                if (!$this->foreignKeyMap->hasModel($pivotModel)) {
                    $this->foreignKeyMap->registerModel($pivotModel);
                }
            }

            $localConfig = [
                'model' => $pivotModel,
                'refModel' => $this->modelName,
                'localKey' => $foreignKey,
            ];

            $otherConfig = [
                'model' => $pivotModel,
                'refModel' => $relatedModel,
                'localKey' => $otherKey,
            ];

            $this->updateForeignKeyFromRelation($otherConfig, $field);
            $this->updateForeignKeyFromRelation($localConfig, $field);
        }
    }

    private function updateForeignKeyFromRelation($fkSettings, $fieldSettings = [])
    {
        $this->foreignKeyMap->updateOrStoreForeignKey($fkSettings, $fieldSettings);
    }

    public function appendInverseRelationship($relationship)
    {
        //todo
    }

    public function registerForeignKeyMap(ForeignKeyMap $foreignKeyMap)
    {
        $this->foreignKeyMap = $foreignKeyMap;
        $foreignKeyMap->registerModel($this->modelName);
    }

    public function compileSchema()
    {
        $foreignKeyMigrationText = [];

        /** @var  SchemaForeignKey[] $localForeignKeys */
        $localForeignKeys = $this->foreignKeyMap->getForeignKeys($this->modelName);
        foreach ($localForeignKeys as $foreignKey) {
            $name = $foreignKey->localKey;
            $this->fields[$foreignKey->localKey] =
                $foreignKey->getFieldRepresentation($this->fields[$name]);
            if (ModelSchema::SEPARATE_FOREIGN_MIGRATION) {
                $dbType = explode(':foreign', $this->fields[$foreignKey->localKey]['dbType']);
                $foreignKeyMigrationText[] = 'foreign'.$dbType[1];
                $this->fields[$foreignKey->localKey]['dbType'] = $dbType[0];
            }
        }
        return ['fields' => $this->fields, 'foreignKeys' => $foreignKeyMigrationText];
    }
}
