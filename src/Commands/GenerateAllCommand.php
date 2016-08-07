<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Motia\Generator\Generators\ForeignKeysMigrationGenerator;
use Motia\Generator\Utils\GeneratorRelationshipInputUtil;

class GenerateAllCommand extends Command
{
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
        // generation configuration for all models
        $command = 'infyom:api_scaffold';
        $generatorOptions = ['paginate' => '15', 'skip' => 'dump-autoload'];
        $generatorAddOns = ['swagger' => true, 'datatable' => true];

        $schemaFilesDirectory = 'resources/model_schemas/';
        $schemaFiles = $this->filesystem->glob($schemaFilesDirectory.'*.json');

        foreach ($schemaFiles as $file) {
            $this->schemas[] = $this->createSchema($file);
        }

        $this->schemas = array_combine(array_column($this->schemas, 'modelName'), $this->schemas);

        $postMigrationsDirectory = 'storage/myschema/post_migrations/';

        $this->deleteObsoleteMigrationFiles();
        $this->compileModelSchemas();

        foreach ($this->schemas as $schema) {
            // TODO skip models,repositories for pivot tables
            $modelName = $schema['modelName'];
            $tableName = $schema['tableName'];
            $fields = $schema['fields'];
            $relationships = $schema['relationships'];

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

        $this->generateForeignKeyMigration();
        $this->call('migrate', []);
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

            $pulledRelationships = GeneratorRelationshipInputUtil::pullRelationships($schemaFields, $modelName);
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

            // populate relevant schemas with deduced foreign and primary keys
            foreach ($foreignKeyFields as $foreignKey) {
                // populates foreign key keys
                $fkOptions = $foreignKey['fkOptions'];
                $fkName = $fkOptions['field'];
                $fkModel = $fkOptions['model'];
                $fkTable = $fkOptions['table'];

                $this->tableFkOptions[$fkTable][$fkName] = $fkOptions;

                if (!array_key_exists($fkModel, $this->schemas)) { // creates schema of a pivot table
                    $this->schemas[$fkModel] = [
                        'modelName'     => $fkModel,
                        'tableName'     => $fkTable,
                        'file'          => null,
                        'relationships' => [],
                        'fields'        => [],
                    ];
                }
                unset($fkSchema);
                $fkSchema = &$this->schemas[$fkModel];
                if (!isset($fkSchema['fields'][$fkName])) {
                    $fkSchema['fields'][$fkName] = [];
                }

                // adds the old field properties(higher priority) than those of the deduced foreign keys
                $fkSchema['fields'][$fkName] =
                    array_merge($foreignKey, $fkSchema['fields'][$fkName]);

                // populates primary keys
                $referencedModel = $fkOptions['referencedModel'];
                $referencedField = $fkOptions['references'];

                unset($referencedSchema);
                $referencedSchema = &$this->schemas[$referencedModel];

                if (!isset($referencedSchema['fields'][$referencedField])) {
                    $referencedSchema['fields'][$referencedField] = [];
                }
                $referencedSchema['fields'][$referencedField] =
                    array_merge($this->createPrimaryKey($referencedField), $referencedSchema['fields'][$referencedField]);
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

    public function generateForeignKeyMigration()
    {
        $fkMigrationGenerator = new ForeignKeysMigrationGenerator($this->tableFkOptions);
        $file = $fkMigrationGenerator->generate();

        $this->comment("\nMigration created: ");
        $this->info($file);
    }

    public function createSchema($file, $modelName = null, $tableName = null)
    {
        if (is_null($modelName) && isset($file)) {
            $modelName = studly_case(str_singular($this->filesystem->name($file)));
        }
        if (is_null($tableName) && isset($modelName)) {
            $tableName = Str::snake(Str::plural($modelName));
        }
        $fields = [];
        $relationships = [];

        return compact('file', 'modelName', 'tableName', 'fields', 'relationships');
    }

    public function createPrimaryKey($fieldName)
    {
        return [
            'fieldInput'  => $fieldName.':increments',
            'htmlType'    => '',
            'validations' => '',
            'searchable'  => false,
            'fillable'    => false,
            'primary'     => true,
            'inForm'      => false,
            'inIndex'     => false,
        ];
    }
}
