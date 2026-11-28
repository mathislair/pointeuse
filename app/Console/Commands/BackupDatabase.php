<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Consistent copy of the SQLite file (VACUUM INTO works while the app runs),
 * next to the database in a "backups" folder; the oldest copies are pruned.
 */
class BackupDatabase extends Command
{
    protected $signature = 'pointeuse:backup {--keep=30 : Nombre de sauvegardes conservées}';

    protected $description = 'Sauvegarde la base SQLite (copie cohérente) et purge les anciennes';

    public function handle(): int
    {
        $database = config('database.connections.sqlite.database');
        if ($database === ':memory:' || ! is_file($database)) {
            $this->warn('Pas de fichier SQLite à sauvegarder.');

            return self::SUCCESS;
        }

        $dir = dirname($database).'/backups';
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            $this->error("Impossible de créer $dir");

            return self::FAILURE;
        }

        $target = sprintf('%s/pointeuse-%s.sqlite', $dir, now()->format('Y-m-d_His'));
        DB::statement('VACUUM INTO '.DB::getPdo()->quote($target));
        $this->info("Sauvegarde écrite : $target");

        $files = glob($dir.'/pointeuse-*.sqlite') ?: [];
        sort($files);
        foreach (array_slice($files, 0, max(0, count($files) - (int) $this->option('keep'))) as $old) {
            @unlink($old);
        }

        return self::SUCCESS;
    }
}
