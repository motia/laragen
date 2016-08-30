<?php


namespace InfyOm\Generator\Common;

use Illuminate\Support\Str;

class ModelSchema
{
    public $fileName;
    public $modelName;
    public $tableName;
    public $fields;
    public $relationships;
    public $deducedForeignKeys;
    protected $primaryKey;
    private $parsed = false;

    public function __construct($schemaFile = null, $modelName = null, $tableName = null)
    {
        if (isset($schemaFile)) {
            $this->fileName = $schemaFile;
        }

        if (is_null($modelName) && isset($file)) {
            // take the model_schema file name as the model name
            $baseName = basename($file, '.json');
            $this->modelName = Str::studly(Str::singular($baseName));
        }

        if (is_null($tableName) && isset($modelName)) {
            $this->tableName = Str::snake(Str::plural($modelName));
        }
    }

    /**
     * @return mixed
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @param mixed $primaryKey
     */
    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    public function parseDataFromFile()
    {
        $jsonData = $this->getSchemaRawData();
        $this->parseData($jsonData);
    }

    private function getSchemaRawData()
    {
        if(isset($this->fileName))
            return json_decode(file_get_contents($this->fileName), true);

        return [];
    }

    private function parseData(array $jsonData)
    {
        foreach ($jsonData as $field) {
            $fieldType = self::getFieldType($field);
            if ($fieldType == 'field' || $fieldType == 'primary') {
                // fields are indexed for simpler search later
                $fieldName = self::getFieldName($field['fieldInput']);
                $this->fields[$fieldName] = $field;

                if ($fieldType == 'primary') {
                    // TODO check for primary key inconsistency
                    $this->setPrimary($fieldName);
                }

            } elseif ($this->getFieldType($field) == 'relationship') {
                $this->parseRelationship($field);
            }
        }

        $this->parsed = true;
    }

    private static function getFieldType($field)
    {
        // TODO
        // returns 'field', 'relationship', 'primary' ,'index'
        return 'field';
    }

    public static function getFieldName($field)
    {
        return strtok($field['fieldInput'], ':');
    }

    /**
     * @param $fieldName
     * @param bool $checkCollision
     * @return bool
     */
    private function setPrimary($fieldName, $checkCollision = true)
    {
        if ($fieldName != $this->primaryKey) {
            return true;
        }

        $this->primaryKey = $fieldName;
        return false;
    }

    private function parseRelationship($field)
    {
        // TODO parse relationship
        // TODO fill the foreign keys and relationships attributes
    }

    /**
     * @return boolean
     */
    public function isParsed()
    {
        return $this->parsed;
    }

    public function getDeducedForeignKeys()
    {
        return $this->deducedForeignKeys;
    }

    public function mergeForeignKeyFields(CompilationSchema $otherSchema, $fkName)
    {

    }

    public function appendInverseRelationship($relationship){

    }
}