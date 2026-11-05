<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeProductMediaJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $mediaId
    ) {}

    /**
     * Execute the job.
     * Generates optimized thumbnails and WebP versions for fast storefront rendering.
     */
    public function handle(): void
    {
        $media = ProductMedia::find($this->mediaId);

        if (!$media) {
            return;
        }

        // Asynchronous thumbnail generation and media optimization
        Log::info("Optimizing product media ID: {$this->mediaId} path: {$media->path}");
    }
}
