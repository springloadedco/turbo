<?php

namespace Springloaded\Turbo\Commands\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait DisplaysCommands
{
    /**
     * Display a formatted command line to the user.
     *
     * Strips the single-quote escaping that Symfony Process adds
     * around each argument for a cleaner display.
     */
    protected function displayCommand(Process $process): void
    {
        $this->info(Str::remove("'", $process->getCommandLine()));
    }
}
