<?php


namespace Motia\Generator\Common;

use Illuminate\Support\Str;
use Motia\Generator\Common\ForeignKeyMap;
use Motia\Generator\Common\SchemaForeignKey;
use Symfony\Component\Console\Exception\RuntimeException;


function hashPivot($model1, $model2)
{
    if ($model1 < $model2) {
        return $model1 . '.' . $model2;
    } else {
        return $model2 . '.' . $model1;
    }
}

class ModelSchema
{
    /** @var ModelSchema[] */
    public static $modelSchemas = [];
    /** @var bool[] */
    public static $pivotHasModel = [];
    /** @var string  */
    public static $wildCard = '?';
    /** @var ForeignKeyMap */
    public $foreignKeyMap;
    /**
     * @var null|string
     */
    public $file;
    public $modelName;
    public $tableName;

    /** @var  string */
    protected $primaryKey;
    /** @var array  */
    public $fields = [];
    public $relationships = [];

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

        if (isset($this->modelName)) {
            self::$modelSchemas[$this->modelName] = $this;
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
        if (empty($this->primaryKey) || $this->primaryKey == self::$wildCard) {
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
            // TODO catch fileIO exception
            $jsonData = json_decode(file_get_contents($this->file), true);
            $this->parseData($jsonData);
        } else {
            throw new RuntimeException("no file name for model/table $this->modelName/$this->tableName");
        }
    }

    /**
     * @param array $jsonData
     */
    private function parseData(array $jsonData)
    {
        foreach ($jsonData as $field) {
            $fieldType = self::getFieldType($field);

            if ($fieldType == 'field' || $fieldType == 'primary') {
                // fields are indexed for simpler search later
                $fieldName = $field['name'];
                $this->fields[$fieldName] = $field;

                if ($fieldType == 'primary') {
                    // TODO handle primary key inconsistency exception
                    $this->setPrimaryKey($field['name']);
                }

            } elseif ($fieldType == 'relation') {
                $this->parseRelationship($field);
            } else {
                throw new RuntimeException("wrong field type inside $this->file: \n"
                    . json_encode($field));
            }
        }
    }

    /**
     * @param array $field
     * @return string
     */
    private static function getFieldType(array $field)
    {
        if (isset($field['primary'])) {
            return 'primary';
        }

        if (isset($field['relation'])) {
            return 'relation';
        }

        if (isset($field['name'])) {
            return 'field';
        }

        return '';
    }

    /**
     * @param array $field
     */
    private function parseRelationship(array $field)
    {
        dump($this->modelName);
        $relationInputs = explode(',', $field['relation']);

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
            ]);

        } elseif ($relationType == '1tm' || $relationType == '1t1') {
            // hasOne and hasMany relations
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $this->updateForeignKeyFromRelation([
                'model' => $relatedModel,
                'refModel' => $this->modelName,
                'localKey' => $foreignKey,
                'otherKey' => $otherKey,
            ]);
        } elseif ($relationType == 'mtm') {
            // belongsToMany relations
            $pivotModel = array_pull($field, 'pivotModel'); // can be null
            if ($pivotModel === null) {
                $pivotModel = hashPivot($this->modelName, $relatedModel);
                self::$pivotHasModel[$pivotModel] = false;
            } else {
                self::$pivotHasModel[$pivotModel] = true;
            }
            $pivotTable = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $localConfig = [
                'model' => $pivotModel,
                'refModel' => $this->modelName,
                'otherKey' => $foreignKey,
                'table' => $pivotTable,
            ];

            $otherConfig = [
                'model' => $pivotModel,
                'refModel' => $relatedModel,
                'otherKey' => $otherKey,
                'table' => $pivotTable,
            ];

            $this->updateForeignKeyFromRelation($otherConfig);
            $this->updateForeignKeyFromRelation($localConfig);
        }
    }

    private function updateForeignKeyFromRelation($fkSettings, $fieldSettings = [])
    {
        $this->foreignKeyMap->updateOrStoreForeignKey($fkSettings, $fieldSettings, false);
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

}
