<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class GenerateAllCommand extends Command
{
    use ArtisanCommandTrait;
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
        
        $filesystem = new Filesystem;
        $migrationFiles = glob('database/migrations/*.php');
        foreach ($migrationFiles as $migFile) {
            if( !str_contains($migFile, 'create_users_table') && !str_contains($migFile, 'create_password_resets_table') )
                $filesystem->delete($migFile);
        }

        $directory = 'storage/myschema/table_descrp/';

        $jsonFiles = $filesystem->glob($directory.'*.json');
        

        foreach($jsonFiles as $file){
            $fileName = $filesystem->name($file);

            $model = studly_case(str_singular($fileName));
            $tableName = $fileName;
            
            $options = [
                    'model' => $model,
                    '--fieldsFile' => $file,
                    '--tableName' => $tableName,
                    '--skip' => 'dump-autoload',
                    '--paginate' => 15,
                    '-n' => null
                ];

            $command = 'infyom:api';
            $output = $this->executeArtisanCommand($command, $options);        
            echo $output;
        }

        $directory = 'storage/myschema/post_migrations/';
        $filesToCopy = $filesystem->glob($directory.'*.php');

        foreach($filesToCopy as $file){
            $filesystem->copy($file, 'database/migrations/' . date('Y_m_d_His')  . '_' . basename($file));
        }        
    }



}
