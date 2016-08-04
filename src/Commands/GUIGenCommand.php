<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Motia\Generator\Utils\GeneratorRelationshipInputUtil;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


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

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new Filesystem;;
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
        $schemaFiles = $this->filesystem->glob($schemaFilesDirectory . '*.json');

        $schemas = array_map(
            function($file){
                $modelName = studly_case(str_singular($this->filesystem->name($file)));
                $tableName = Str::snake(Str::plural($modelName));
                return compact('file', 'modelName', 'tableName');
            },
            $schemaFiles
        );

        $schemas = array_combine(array_column($schemas, 'modelName'), $schemas);

        $postMigrationsDirectory = 'storage/myschema/post_migrations/';

        $this->deleteObsoleteMigrationFiles();

        // compiles schemas
        $compiledModelSchemas = $this->compileModelSchemas($schemas);
        //dd($compiledModelSchemas);
        //dd(json_encode($modelSchemas));

        //die(json_encode($compiledModelSchemas));

        foreach ($compiledModelSchemas as $compiledModelSchema){
            $modelName = $compiledModelSchema['modelName'];
            $tableName = $compiledModelSchema['tableName'];
            $fields = &$compiledModelSchema['fields'];
            $relationships = $compiledModelSchema['relationships'];

            $jsonData = [
                'migrate' => true,
                'fields' => $fields,
                'relationships' => $relationships,
                'tableName' => $tableName,
                'options' => $generatorOptions,
                'addOns' => $generatorAddOns,
            ];

            $options = [
                'model' => $modelName,
                '--jsonFromGUI' => json_encode($jsonData),
            ];

            $this->call($command, $options);
        }

        // project specific
        // copies some migrations files
        $filesToCopy = $this->filesystem->glob($postMigrationsDirectory . '*.php');

        foreach ($filesToCopy as $file) {
            $this->filesystem->copy($file, 'database/migrations/' . date('Y_m_d_His') . '_' . basename($file));
        }
    }

    public function deleteObsoleteMigrationFiles(){
        $migrationFiles = glob('database/migrations/*.php');

        // delete all generated migration files
        foreach ($migrationFiles as $migrationFile) {
            if (!str_contains($migrationFile, 'create_users_table') && !str_contains($migrationFile, 'create_password_resets_table'))
                $this->filesystem->delete($migrationFile);
        }
    }

    //  mutates modelSchemas, add
    //  NOTE: fields defined in the schemaFile properties override those of the deduced foreign keys
    public function compileModelSchemas($schemas){

        foreach ($schemas as $modelName => &$schema) {
            $file = $schema['file'];

            $fileContents = file_get_contents($file);
            $schemaFields = json_decode($fileContents, true);

            // returns $model => [$relationship1, $relationship2...]
            $pulledRelationships = GeneratorRelationshipInputUtil::pullRelationships($schemaFields, $modelName);
            // echo json_encode($pulledRelationships);
            $foreignKeyFields = GeneratorRelationshipInputUtil::getForeignKeysColumn($pulledRelationships);

            $indexedSchemaFields = [];

            // indexing fields
            foreach ($schemaFields as &$schemaField){
                if(isset($schemaField['fieldInput'])) {
                    $fieldName = strtok($schemaField['fieldInput'], ':');
                    $indexedSchemaFields[$fieldName] = $schemaField;
                }
            }

            //dd($pulledRelationships);
            //dd($foreignKeyfields);
            $schema['fields'] = $indexedSchemaFields;
            $schema['relationships'] = $pulledRelationships;

            // populate relevant models deduced foreign
            foreach ($foreignKeyFields as $foreignKey) {
                $fkName = $foreignKey['fkOptions']['field'];
                $fkModel = $foreignKey['fkOptions']['model'];

                $referencedSchema = &$schemas[$fkModel];
                if(!isset($referencedSchema['fields'][$fkName])){
                    $referencedSchema['fields'][$fkName] = [];
                }

                // adds the old field properties(higher priority) to the deduced foreign keys
                $referencedSchema['fields'][$fkName] =
                    array_merge($foreignKey, $referencedSchema['fields'][$fkName]);
                //dd($referencedSchema);
            }

            //$this->json_die($schema);

            foreach ($schema['fields'] as $field) {
                $fieldName = strtok($field['fieldInput'], ':');

                if(isset($schema['fields'][$fieldName]))
                    // override fk fieldInput properties from schema file
                    $schema['fields'][$fieldName] =
                        array_merge($schema['fields'][$fieldName], $field);
                else
                    // create new fieldInput from schema file
                    $schema['fields'][$fieldName] = $field;
            }
        }
        return $schemas;
        //$this->json_die($schemas);
    }

    public function json_die($var){
        die(json_encode($var));
    }
}
