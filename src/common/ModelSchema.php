<?php


namespace Motia\Generator\Common;

use Illuminate\Support\Str;
use Motia\Generator\Commands\GenerateAllCommand;
use Symfony\Component\Console\Exception\RuntimeException;


class ModelSchema
{
    const WILD_CARD = '?';
    const SEPARATE_FOREIGN_MIGRATION = true;

    /** @var bool[] */
    /** @var ForeignKeyMap */
    public $foreignKeyMap;
    /** @var  GenerateAllCommand $command */
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
                $relation = $this->parseRelationField($relationPart);
                $this->relationships[] = $relation;
                if ($this->createRelationInverse) {
                    $this->appendInverseRelationship($relation);
                }
            }
        }
    }

    private static function getFieldPart(array $field)
    {
        if (isset($field['dbType'])) {
            $field['dbType'] = explode(':foreign', $field['dbType'])[0];
            return array_except($field, ['type', 'relation']);
        } else {
            return null;
        }
    }

    private static function isPrimary(array $field)
    {
        return isset($field['primary']);
    }

    private static function getRelationPart(array $field)
    {
        if (isset($field['relation'])) {
            return array_only($field, ['type', 'relation', 'dbType']);
        }
        return null;
    }

    /**
     * @param array $field
     */
    private function parseRelationField(array $field)
    {
        $relation = array_pull($field, 'relation');
        $relationInputs = explode(',', $relation);

        $relationType = array_shift($relationInputs);
        $relatedModel = array_shift($relationInputs);

        if ($relationType == 'mt1') {
            // belongsTo relations
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $fk = $this->updateForeignKeyFromRelation([
                'model' => $this->modelName,
                'refModel' => $relatedModel,
                'localKey' => $foreignKey,
                'otherKey' => $otherKey,
            ], $field);

            return [
                'type' => $relationType,
                'related' => $relatedModel,
                'foreign' => $fk,
            ];
        } elseif ($relationType == '1tm' || $relationType == '1t1') {
            // hasOne and hasMany relations
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $fk = $this->updateForeignKeyFromRelation([
                'model' => $relatedModel,
                'refModel' => $this->modelName,
                'localKey' => $foreignKey,
                'otherKey' => $otherKey,
            ], $field);

            return [
                'type' => $relationType,
                'related' => $relatedModel,
                'foreign' => $fk,
            ];
        } elseif ($relationType == 'mtm') {
            // belongsToMany relations
            $pivotTable = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $foreignKey = (empty($relationInputs)) ? null : array_shift($relationInputs);
            $otherKey = (empty($relationInputs)) ? null : array_shift($relationInputs);

            $pivotModel = array_pull($field, 'pivotModel'); // can be null
            if ($pivotModel === null) {
                // todo maybe option
                $pivotModel = min($this->modelName, $relatedModel) . max($this->modelName, $relatedModel) . 'Pivot';
                if($pivotTable === null)
                    $pivotTable = snake_case(str_singular(min($this->modelName, $relatedModel)).
                        str_singular(max($this->modelName, $relatedModel)));
                $pivotSchema = new ModelSchema(null, $pivotModel, $pivotTable);
                if ($this->foreignKeyMap !== null) {
                    $pivotSchema->foreignKeyMap = $this->foreignKeyMap;
                    $this->foreignKeyMap->registerModel($pivotModel);
                }
                $this->command->schemas[$pivotModel] = $pivotSchema;
                $this->command->pivots[] = $pivotSchema->modelName;
            }

            $localConfig = [
                'model' => $pivotModel,
                'refModel' => $this->modelName,
            ];
            if (!empty($foreignKey)) {
                $localConfig['localKey'] = $foreignKey;
            }

            $otherConfig = [
                'model' => $pivotModel,
                'refModel' => $relatedModel,
            ];

            if (!empty($otherKeyKey)) {
                $otherConfig['localKey'] = $otherKey;
            }

            $fk1 = $this->updateForeignKeyFromRelation($otherConfig, $field);
            $fk2 = $this->updateForeignKeyFromRelation($localConfig, $field);

            return [
                'type' => $relationType,
                'related' => $relatedModel,
                'foreign' => [$fk1, $fk2],
            ];
        }
        return null;
    }

    private function updateForeignKeyFromRelation($fkSettings, $fieldSettings = [])
    {
        return $this->foreignKeyMap->updateOrStoreForeignKey($fkSettings, $fieldSettings);
    }

    public function appendInverseRelationship($relationship)
    {
        $type = $relationship['type'];
        $related = $relationship['related'];
        $foreign = $relationship['foreign'];
        if (is_array($foreign)) {
            $foreign = array_reverse($foreign);
        }
        foreach ($this->command->schemas[$related]->relationships as $other) {
            if ($other['type'] == strrev($type) && $other['related'] == $this->modelName) {
                return;
            }
        }

        $this->command->schemas[$related]->relationships[] = [
            'type' => strrev($type),
            'related' => $this->modelName,
            'foreign' => $foreign,
        ];
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
            if (isset($this->fields[$name])) {
                $field = $this->fields[$name];
            } else {
                $field = [];
            }
            $this->fields[$foreignKey->localKey] =
                $foreignKey->getFieldRepresentation($field, ModelSchema::SEPARATE_FOREIGN_MIGRATION);

            if (ModelSchema::SEPARATE_FOREIGN_MIGRATION) {
                dump('rrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrr');
                $foreignKeyMigrationText[] = array_pull($this->fields[$foreignKey->localKey], 'foreignKey');
                dump('gggggggggggggggggggggggggggggggggggggggggg');
            }
        }

        $relations = [];
        foreach ($this->relationships as $relationship) {
            $relationText = $relationship['type'] . ',' . $relationship['related'];
            if (in_array($relationship['type'], ['1t1', '1tm', 'mt1'])) {
                $fk = $relationship['foreign'];
                if (!$fk->defaulted['otherKey']) {
                    $relationText .= ',' . $fk->localKey . ',' . $fk->otherKey;
                } else {
                    if (!$fk->defaulted['localKey']) {
                        $relationText .= ',' . $fk->localKey;
                    }
                }
            } elseif ($relationship['type'] == 'mtm') {
                list($fk1, $fk2) = $relationship['foreign'];
                $pivotTable = $this->command->schemas[$fk2->model]->tableName;

                $relationText .= ',' . $pivotTable;
                if (!$fk2->defaulted['localKey']) {
                    $relationText .= ',' . $fk1->localKey . ',' . $fk2->localKey;
                } elseif (!$fk1->defaulted['localKey']) {
                    $relationText .= ',' . $fk1->localKey;
                }
            }
            if ($relationship['type'] != 'mt1') {
                $relation['type'] = 'relation';
                $relations[] = [
                    'type' => 'relation',
                    'relation' => $relationText,
                ];
            } else {
                $localKey = $relationship['foreign']->localKey;
                $this->fields[$localKey]['relation'] = $relationText;
            }
        }

        return ['fields' => array_merge($relations, $this->fields), 'foreignKeys' => $foreignKeyMigrationText];
    }


    public function createPrimary()
    {
        $this->primaryKey = 'id';
        return [
            'name' => $this->primaryKey, // todo default option
            'dbType' => 'increments',
            'htmlType' => '',
            'validations' => '',
            'searchable' => false,
            'fillable' => false,
            'primary' => true,
            'inForm' => false,
            'inIndex' => false
        ];
    }
}
