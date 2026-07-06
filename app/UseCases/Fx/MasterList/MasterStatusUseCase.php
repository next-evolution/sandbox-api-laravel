<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Services\MasterCacheService;

class MasterStatusUseCase
{
    public function __construct(private readonly MasterCacheService $masterCacheService) {}

    public function execute(): string
    {
        return $this->masterCacheService->status();
    }
}
