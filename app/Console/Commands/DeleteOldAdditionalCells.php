<?php

namespace App\Console\Commands;

use App\Models\Admin\AdditionalCells;
use Illuminate\Console\Command;

class DeleteOldAdditionalCells extends Command
{
    protected $signature = 'additionalCells:clean';
    protected $description = 'Видалення застарілих комірок (timestamp)';

    public function handle()
    {
        $deleted = AdditionalCells::where('start', '<', now())->delete();

        $this->info("Успішно видалено комірок: {$deleted}");
    }
}
