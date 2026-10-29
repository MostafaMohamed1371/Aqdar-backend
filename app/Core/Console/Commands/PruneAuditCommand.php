<?php

namespace App\Core\Console\Commands;

use App\Modules\Dashboard\Services\AuditSearchService;
use Illuminate\Console\Command;

class PruneAuditCommand extends Command
{
    protected $signature = 'aqdar:prune-audit';

    protected $description = 'Delete audit and access logs older than the configured retention';

    public function handle(AuditSearchService $audit): int
    {
        $deleted = $audit->pruneExpired();
        $this->info('pruned='.$deleted);

        return self::SUCCESS;
    }
}
