<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;

class TurboCommand extends Command
{
    public $signature = 'turbo';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
