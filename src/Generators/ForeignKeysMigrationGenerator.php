<?php
/**
 * Created by PhpStorm.
 * User: M
 * Date: 8/6/2016
 * Time: 1:21 AM
 */

namespace Motia\Generator\Generators;

use File;
use InfyOm\Generator\Common\CommandData;
use InfyOm\Generator\Utils\FileUtil;
use InfyOm\Generator\Utils\SchemaUtil;
use InfyOm\Generator\Utils\TemplateUtil;

class ForeignKeysMigrationGenerator
{
    /** @var CommandData */
    private $tableFKsOptions;

    /** @var string */
    private $path;

    public function __construct($tableFKsOptions)
    {
        $this->tableFKsOptions = $tableFKsOptions;
        $this->path = config('infyom.laravel_generator.path.migration', base_path('database/migrations/'));
    }

    public function generate()
    {
        $templateData = TemplateUtil::getTemplate('relationships.foreign_key_Migration', 'laravel-generator');

        $tables = $this->generateTables();

        $templateData = str_replace('$UP_TABLES$', $tables['upTables'], $templateData);

        $templateData = str_replace('$DOWN_TABLES$', $tables['downTables'], $templateData);

        $fileName = date('Y_m_d_His').'_add_foreign_key_constraints.php';

        FileUtil::createFile($this->path, $fileName, $templateData);

        //$this->commandData->commandComment("\nMigration created: ");
        //$this->commandData->commandInfo($fileName);
    }

    private function generateTables()
    {
        $upTables = [];
        $downTables = [];

        foreach ($this->tableFKsOptions as $table => $FKOptions) {
            $upTables[] = $this->generateUpTable($table);
            $downTables[] = $this->generateDownTable($table);
        }

        return [
            'upTables' => implode(infy_nl(), $upTables),
            'downTables' => implode(infy_nl(), $downTables)
        ];
    }

    public function rollback()
    {
        // TODO
    }

    private function generateUpTable($table)
    {
        $templateData = TemplateUtil::getTemplate('relationships.table_update', 'laravel-generator');
        $templateData = str_replace('$TABLE_NAME$', $table, $templateData);

        $fields = [];

        foreach ($this->tableFKsOptions[$table] as $FKOption){
            $field = $this->createAddForeignKeyField($FKOption);
            $fields[] = SchemaUtil::createField($field);
        }
        $fields = implode(infy_nl_tab(1, 3), $fields);
        $templateData = str_replace('$FIELDS$', $fields, $templateData);
        return $templateData;
    }

    public function createAddForeignKeyField($fkOptions){
        return [
            'fieldName' => $fkOptions['field'],
            'databaseInputs' =>
                'foreign:references,'."'".$fkOptions['references']."'"
                .':on,'."'".$fkOptions['on']."'"
                .':onUpdate,'."'".$fkOptions['onUpdate']."'"
                .':onDelete,'."'".$fkOptions['onDelete']."'"
        ];
    }

    private function generateDownTable($table)
    {
        $templateData = TemplateUtil::getTemplate('relationships.table_update', 'laravel-generator');
        $templateData = str_replace('$TABLE_NAME$', $table, $templateData);

        $fields = [];

        foreach ($this->tableFKsOptions[$table] as $FKOption){
            $field = $this->createDropForeignKeyField($FKOption);
            $fields[] = SchemaUtil::createField($field);
        }

        $fields = implode(infy_nl_tab(1, 3), $fields);
        $templateData = str_replace('$FIELDS$', $fields, $templateData);
        return $templateData;
    }

    public function createDropForeignKeyField($fkOptions){
        return [
            'fieldName' => $fkOptions['field'],
            'databaseInputs' => 'dropForeign',
        ];
    }
}
