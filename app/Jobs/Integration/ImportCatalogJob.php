<?php

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Models\CatalogImport;
use App\Services\Integration\CatalogImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        public CatalogImport $import,
        public ?array $mapping = null
    ) {}

    public function handle(CatalogImportService $service): void
    {
        $service->processImport($this->import, $this->mapping, false);
    }
}
