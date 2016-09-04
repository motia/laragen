<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Filesystem\Filesystem;
use InfyOm\Generator\Utils\FileUtil;
use Motia\Generator\Common\ForeignKeyMap;
use Motia\Generator\Common\ModelSchema;
use Motia\Generator\Generators\ForeignKeysMigrationGenerator;

class GenerateAllCommand extends Command
{
    use ArtisanCommandTrait;
    /**
     * @var ModelSchema[]
     */
    public $schemas;
    public $pivots = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'motia:generate';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generates models and migration from json files';
    /** @var Composer */
    protected $composer;
    /** @var Filesystem */
    protected $filesystem;
    /**
     * @var ForeignKeyMap
     */
    protected $foreignKeyMap;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->composer = app()['composer'];
        $this->filesystem = new Filesystem();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // generation configuration for all models
        $command = 'infyom:api_scaffold';
        $generatorOptions = ['paginate' => '15', 'skip' => 'dump-autoload'];
        $generatorAddOns = ['swagger' => false, 'datatable' => false];

        $schemaFilesDirectory = 'resources/model_schemas/';
        $schemaFiles = $this->filesystem->glob($schemaFilesDirectory . '*.json');

        $this->foreignKeyMap = new ForeignKeyMap();
        foreach ($schemaFiles as $file) {
            $modelSchema = new ModelSchema($file);
            $modelSchema->registerForeignKeyMap($this->foreignKeyMap);

            $this->schemas[$modelSchema->modelName] = $modelSchema;
        }

        $this->deleteObsoleteMigrationFiles();
        $this->compileModelSchemas();

        foreach ($this->schemas as $schema) {
            // TODO skip models,repositories for pivot tables
            $modelName = $schema->modelName;
            $tableName = $schema->tableName;
            $fields = $schema->fields;
            //$relationships = $schema->relationships;

            $jsonData = [
                'migrate' => false,
                'fields' => $fields,
                //  'relationships' => $relationships,
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

        // todo adapt FKMigrationGenerator to changes
        //$this->generateForeignKeyMigration();
        // todo check for an option to migrate after finishing ALL generation or after each stage
        //$this->call('migrate', []);


        $this->info('Generating autoload files');


        $this->composer->dumpOptimized();
    }

    public function deleteObsoleteMigrationFiles()
    {
        $migrationFiles = glob('database/migrations/*.php');

        // delete all generated migration files
        foreach ($migrationFiles as $migrationFile) {
            if (!str_contains($migrationFile, 'create_users_table')
                && !str_contains($migrationFile, 'create_password_resets_table')
            ) {
                $this->filesystem->delete($migrationFile);
            }
        }
    }

    // fills the schemas and tableFkOptions attributes
    public function compileModelSchemas()
    {
        foreach ($this->schemas as $schema) {
            $schema->parseDataFromFile();
        }

        $tableForeignKeys = [];
        $modelSchemas = [];
        foreach ($this->schemas as &$schema) {
            $compileResults = $schema->compileSchema();
            $modelSchemas[$schema->modelName] = $compileResults['fields'];
            if (!empty($compileResults['foreignKeys'])) {
                $tableForeignKeys[$schema->tableName] = $compileResults['foreignKeys'];
            }
        }


        return array($modelSchemas, $tableForeignKeys);
    }

    public function generateForeignKeyMigration($tableForeignKeys)
    {
        $fkMigrationGenerator = new ForeignKeysMigrationGenerator($tableForeignKeys);
        $file = $fkMigrationGenerator->generate();

        $this->comment("\nMigration created: ");
        $this->info($file);
    }

    public function createPrimaryKey($fieldName)
    {
        return [
            'fieldInput' => $fieldName . ':increments',
            'htmlType' => '',
            'validations' => '',
            'searchable' => false,
            'fillable' => false,
            'primary' => true,
            'inForm' => false,
            'inIndex' => false,
        ];
    }
}
