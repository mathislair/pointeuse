<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use Illuminate\Console\Command;

class CloseForgottenClockings extends Command
{
    protected $signature = 'pointeuse:close-forgotten';

    protected $description = 'Clôture les pointages dont le départ a été oublié (fin du créneau + délai de grâce)';

    public function handle(): int
    {
        $n = Assignment::closeForgotten();
        $this->info("$n pointage(s) clôturé(s).");

        return self::SUCCESS;
    }
}
