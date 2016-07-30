<?php

namespace Motia\Generator\Commands;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

trait ArtisanCommandTrait{
    public function executeArtisanCommand($command, $options){
        $stmt = 'php artisan '. $command . ' ' . $this->prepareOptions($options);
        
        $process = new Process($stmt);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return $process->getOutput();
    }

    public function prepareOptions($options){
            $args = [];
            $opts = [];
            $flags = [];
            foreach ($options as $key => $value) {
                if(ctype_alpha(substr($key, 0, 1)))
                    $args[] = $value;

                else if(starts_with($key, '--')){
                    $opts[] = $key. (is_null($value) ? '' : '=' . $value) ;
                }

                else if(starts_with($key, '-')){
                    $flags[] = $key;
                }
            }

            return   implode(' ', $args) . ' '
                    .implode(' ', $opts). ' '
                    .implode(' ', $flags);
    }
}