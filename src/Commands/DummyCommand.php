<?php

namespace Motia\Generator\Commands;

use Illuminate\Console\Command;

class DummyCommand extends Command
{
    use ArtisanCommandTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'motia:dummy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'echos a dummy quote';

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
        echo 'Today, I don\'t have to be a Murloc :)';
    }

}
