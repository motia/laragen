<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Motia\Generator\Utils\GeneratorRelationshipInputUtil;
use RuntimeException;
use Exception;

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

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $command = 'infyom:api_scaffold';
        $generatorOptions = ['paginate' => '15', 'skip' => 'dump-autoload'];
        $generatorAddOns = ['swagger' => true, 'datatable' => true];

        $filesystem = new Filesystem;
        $migrationFiles = glob('database/migrations/*.php');
        $primaryKeys = [];

        // delete all generated migration files
        foreach ($migrationFiles as $migrationFile) {
            if (!str_contains($migrationFile, 'create_users_table') && !str_contains($migrationFile, 'create_password_resets_table'))
                $filesystem->delete($migrationFile);
        }

        $schemaFilesDirectory = 'resources/model_schemas/';
        $schemaFiles = $filesystem->glob($schemaFilesDirectory . '*.json');

        $fieldInputsArray = [];
        $relationships = [];

        // prepare each model from its schemaFile
        foreach ($schemaFiles as $file) {
            var_dump('Log(file) = '. $file);

            $schemaModel = studly_case(str_singular($filesystem->name($file)));

            $fileContents = file_get_contents($file);

            $jsonData = json_decode($fileContents, true);

            // returns $model => [$relationship1, $relationship2...]
            $pulledRelationships = GeneratorRelationshipInputUtil::pullRelationships($jsonData);
            $foreignKeyInputFields = GeneratorRelationshipInputUtil::pullForeignKeysColumn($pulledRelationships) ;
            $relationships += $pulledRelationships;

            foreach ($foreignKeyInputFields as $foreignKey) {
                $fkName = $foreignKey['fieldName'];
                $modelName = array_pull($foreignKey, 'modelName');
                // adds field options to the deduced foreign keys
                // options have less priority than the inputFields options
                $fieldInputsArray[$modelName][$fkName] += $foreignKey;
            }

            foreach ($jsonData as $fieldInput) {
                $fieldInputName = strtok($fieldInput['fieldInput'], ':');

                if(isset($fieldInputsArray[$schemaModel][$fieldInputName]))
                    $oldInputField = $fieldInputsArray[$schemaModel][$fieldInputName];
                else $oldInputField = [];

                $fieldInputsArray[$schemaModel][$fieldInputName] = $fieldInput + $oldInputField;
            }
        }

        // remove keys for input fields
        $fieldInputsArray = array_map('array_values', $fieldInputsArray);
        //dd($fieldInputsArray);
        foreach ($fieldInputsArray as $model => $fieldInputs){
            var_dump("Log(model=$model)");

            $jsonData = [
                'migrate' => true,
                'fields' => $fieldInputs,
                'options' => $generatorOptions,
                'addOns' => $generatorAddOns,
            ];

            $options = [
                'model' => $model,
                '--jsonFromGUI' => json_encode($jsonData),
            ];
            
            $this->call($command, $options);
        }

        // project specific
        // copies some migrations files

        $postMigrationsDirectory = 'storage/myschema/post_migrations/';
        $filesToCopy = $filesystem->glob($postMigrationsDirectory . '*.php');

        foreach ($filesToCopy as $file) {
            $filesystem->copy($file, 'database/migrations/' . date('Y_m_d_His') . '_' . basename($file));
        }
    }

}
