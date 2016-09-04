<?php

namespace Motia\Generator\Generators;

use InfyOm\Generator\Utils\FileUtil;
use InfyOm\Generator\Utils\SchemaUtil;

class ForeignKeysMigrationGenerator
{

    /*
    /** @var string */
    private $path;
    /** @var  array */
    private $tableForeignKeys;

    public function __construct($tableForeignKeys)
    {
        $this->tableForeignKeys = $tableForeignKeys;
        $this->path = config('infyom.laravel_generator.path.migration', base_path('database/migrations/'));
    }

    public function generate()
    {
        $templateData = $this->getTemplate('foreign_key_migration');

        $tables = $this->generateTables();

        $templateData = str_replace('$UP_TABLES$', $tables['upTables'], $templateData);

        $templateData = str_replace('$DOWN_TABLES$', $tables['downTables'], $templateData);

        $fileName = date('Y_m_d_His', time() + 1).'_add_foreign_key_constraints.php';

        FileUtil::createFile($this->path, $fileName, $templateData);

        //$this->commandData->commandComment("\nMigration created: ");
        //$this->commandData->commandInfo($fileName);
        return $fileName;
    }

    private function generateTables()
    {
        $upTables = [];
        $downTables = [];

        foreach ($this->tableForeignKeys as $table => $FKOptions) {
            $upTables[] = $this->generateUpTable($table);
            $downTables[] = $this->generateDownTable($table);
        }

        return [
            'upTables'   => implode(infy_nl(), $upTables),
            'downTables' => implode(infy_nl(), $downTables),
        ];
    }

    public function rollback()
    {
        // TODO
    }

    private function generateUpTable($table)
    {
        $templateData = $this->getTemplate('table_update');
        $templateData = str_replace('$TABLE_NAME$', $table, $templateData);

        $fields = [];

        foreach ($this->tableForeignKeys[$table] as $FKOption) {
            $field = $this->createAddForeignKeyField($FKOption);
            $fields[] = SchemaUtil::createField($field);
        }
        $fields = implode(infy_nl_tab(1, 3), $fields);
        $templateData = str_replace('$FIELDS$', $fields, $templateData);

        return $templateData;
    }

    public function createAddForeignKeyField($fkOptions)
    {
        $migrationText = 'foreign:references,'."'".$fkOptions['references']."'"
        .':on,'."'".$fkOptions['on']."'";

        if (isset($fkOptions['onUpdate'])) {
            $migrationText .= ':onUpdate,'."'".$fkOptions['onUpdate']."'";
        }
        if (isset($fkOptions['onDelete'])) {
            $migrationText .= ':onDelete,'."'".$fkOptions['onDelete']."'";
        }
        
        return [
            'fieldName'      => $fkOptions['field'],
            'databaseInputs' => $migrationText
        ];
    }

    private function generateDownTable($table)
    {
        $templateData = $this->getTemplate('table_update');
        $templateData = str_replace('$TABLE_NAME$', $table, $templateData);

        $fields = [];

        foreach ($this->tableForeignKeys[$table] as $FKOption) {
            $field = $this->createDropForeignKeyField($table, $FKOption);
            $fields[] = SchemaUtil::createField($field);
        }

        $fields = implode(infy_nl_tab(1, 3), $fields);
        $templateData = str_replace('$FIELDS$', $fields, $templateData);

        return $templateData;
    }

    public function createDropForeignKeyField($table, $fkOptions)
    {
        $constraintName = $table.'_'.$fkOptions['field'].'_foreign';
        return [
            'fieldName'      => $constraintName,
            'databaseInputs' => 'dropForeign',
        ];
    }

    private function getTemplate($template){
        return file_get_contents(base_path('vendor/motia/laragen/templates/'. $template .'.stub'));
    }
}
