<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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
        $generatorOptions = ['paginate' => '15', 'skip' => 'dump-autoload'];
        $generatorAddOns = ['swagger' => true, 'datatable' => true];

        $filesystem = new Filesystem;
        $migrationFiles = glob('database/migrations/*.php');
        foreach ($migrationFiles as $migFile) {
            if( !str_contains($migFile, 'create_users_table') && !str_contains($migFile, 'create_password_resets_table') )
                $filesystem->delete($migFile);
        }

        $directory = 'resources/model_schemas/';

        $jsonFiles = $filesystem->glob($directory.'*.json');

        foreach($jsonFiles as $file){

            $fileContents = file_get_contents($file);
            $jsonData = json_decode($fileContents, true);

            $jsonData = [
                'migrate' => true,
                'fields' => $jsonData,
                'options' => $generatorOptions,
                'addOns' => $generatorAddOns,
            ];
            
            $fileName = $filesystem->name($file);

            $model = studly_case(str_singular($fileName));
            $tableName = $fileName;

            $options = [
                    'model' => $model,
                    '--jsonFromGUI' => json_encode($jsonData),
                ];

            $command = 'infyom:api_scaffold';
            $output = $this->call($command, $options);
        }

        // project specific
        // copies some migrations files

        $directory = 'storage/myschema/post_migrations/';
        $filesToCopy = $filesystem->glob($directory.'*.php');

        foreach($filesToCopy as $file){
            $filesystem->copy($file, 'database/migrations/' . date('Y_m_d_His')  . '_' . basename($file));
        }        
    }

}
