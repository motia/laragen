<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Motia\Generator\Utils\GeneratorRelationshipInputUtil;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GUIGenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'motia:guigen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generates models and migration from json files';

    protected $filesystem;
    protected $schemas;
    protected $tableFkOptions;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $process = new Process('.\resetdb.bat');
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        //echo $process->getOutput() . "\n";

        // generation configuration for all models
        $command = 'infyom:api_scaffold';
        $generatorOptions = ['paginate' => '15', 'skip' => 'dump-autoload'];
        $generatorAddOns = ['swagger' => true, 'datatable' => true];

        $schemaFilesDirectory = 'resources/model_schemas/';
        $schemaFiles = $this->filesystem->glob($schemaFilesDirectory.'*.json');

        $this->schemas = array_map(
            function ($file) {
                $modelName = studly_case(str_singular($this->filesystem->name($file)));
                $tableName = Str::snake(Str::plural($modelName));

                return compact('file', 'modelName', 'tableName');
            },
            $schemaFiles
        );

        $this->schemas = array_combine(array_column($this->schemas, 'modelName'), $this->schemas);

        $postMigrationsDirectory = 'storage/myschema/post_migrations/';

        $this->deleteObsoleteMigrationFiles();

        // compiles schemas
        $this->compileModelSchemas();

        foreach ($this->schemas as $compiledModelSchema) {
            // TODO skip models and stuff of unnecessary pivot tables
            $modelName = $compiledModelSchema['modelName'];
            $tableName = $compiledModelSchema['tableName'];
            $fields = &$compiledModelSchema['fields'];
            $relationships = $compiledModelSchema['relationships'];

            $jsonData = [
                'migrate'       => true,
                'fields'        => $fields,
                'relationships' => $relationships,
                'tableName'     => $tableName,
                'options'       => $generatorOptions,
                'addOns'        => $generatorAddOns,
            ];

            $options = [
                'model'         => $modelName,
                '--jsonFromGUI' => json_encode($jsonData),
            ];

            $this->call($command, $options);
        }

        // TODO function in construction
        $this->generateForeignKeyMigration($this->schemas);

        // project specific
        // copies some migrations files
        $filesToCopy = $this->filesystem->glob($postMigrationsDirectory.'*.php');

        foreach ($filesToCopy as $file) {
            $this->filesystem->copy($file, 'database/migrations/'.date('Y_m_d_His').'_'.basename($file));
        }
    }

    public function deleteObsoleteMigrationFiles()
    {
        $migrationFiles = glob('database/migrations/*.php');

        // delete all generated migration files
        foreach ($migrationFiles as $migrationFile) {
            if (!str_contains($migrationFile, 'create_users_table') && !str_contains($migrationFile, 'create_password_resets_table')) {
                $this->filesystem->delete($migrationFile);
            }
        }
    }

    //  fills the schemas and tableFkOptions attributes
    public function compileModelSchemas()
    {
        foreach ($this->schemas as $modelName => &$schema) {
            $file = $schema['file'];

            $fileContents = ($file) ? file_get_contents($file) : [];
            $schemaFields = json_decode($fileContents, true);

            // returns $model => [$relationship1, $relationship2...]
            $pulledRelationships = GeneratorRelationshipInputUtil::pullRelationships($schemaFields, $modelName);
            // echo json_encode($pulledRelationships);
            $foreignKeyFields = GeneratorRelationshipInputUtil::getForeignKeysColumn($pulledRelationships);

            $indexedSchemaFields = [];

            // indexing fields
            foreach ($schemaFields as &$schemaField) {
                if (isset($schemaField['fieldInput'])) {
                    $fieldName = strtok($schemaField['fieldInput'], ':');
                    $indexedSchemaFields[$fieldName] = $schemaField;
                }
            }

            $schema['fields'] = $indexedSchemaFields;
            $schema['relationships'] = $pulledRelationships;

            // populate relevant models deduced foreign
            foreach ($foreignKeyFields as $foreignKey) {
                $fkOptions = $foreignKey['fkOptions'];
                $fkName = $fkOptions['field'];
                $fkModel = $fkOptions['model'];
                $fkTable = $fkOptions['table'];

                $this->tableFkOptions[$fkTable][$fkName] = $fkOptions;

                if(!key_exists($fkModel, $this->schemas)){ // creates schema of a pivot table
                    $this->schemas[$fkModel] = [
                        'modelName' => $fkModel,
                        'tableName' => $fkTable,
                        'file'      => null,
                        'relationships' => [],
                        'fields' => [],
                    ];
                }
                $referencedSchema = &$this->schemas[$fkModel];


                if (!isset($referencedSchema['fields'][$fkName])) {
                    $referencedSchema['fields'][$fkName] = [];
                }

                // adds the old field properties(higher priority) to the deduced foreign keys
                $referencedSchema['fields'][$fkName] =
                    array_merge($foreignKey, $referencedSchema['fields'][$fkName]);
            }

            foreach ($schema['fields'] as $field) {
                $fieldName = strtok($field['fieldInput'], ':');

                if (isset($schema['fields'][$fieldName])) {
                    // override fk fieldInput properties from schema file
                    $schema['fields'][$fieldName] =
                        array_merge($schema['fields'][$fieldName], $field);
                } else {
                    // create new fieldInput from schema file
                    $schema['fields'][$fieldName] = $field;
                }
            }
        }

    }

    public function foreignKeyConstraint($fk){
        $fkOptions = $fk['fkOptions'];
        return 'foreign,'."'".$fkOptions['field']."'"
            .':references,'."'".$fkOptions['references']."'"
            .':on,'."'".$fkOptions['on']."'"
            .':onUpdate,'."'".$fkOptions['onUpdate']."'"
            .':onDelete,'."'".$fkOptions['onDelete']."'";
    }

    public function json_die($var)
    {
        die(json_encode($var));
    }

    public function generateForeignKeyMigration($compiledSchemas){
        //TODO
    }
}
