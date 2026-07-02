<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Models\AuthUser;
use App\Services\SessionService;
use Illuminate\Support\Facades\Log;

class LogoutUseCase
{
    public function __construct(
        private readonly SessionService $sessionService,
    ) {}

    public function execute(AuthUser $authUser, string $encodedUserId): void
    {
        try {
            $userIdValue = $this->decodeUserId($encodedUserId);

            if ($authUser->sub === $userIdValue) {
                $this->sessionService->deleteBySub($authUser->sub);
            } else {
                Log::error(sprintf(
                    'logout failed [REQ:UserId:%s|TOKEN:UserId:%s]',
                    $userIdValue,
                    $authUser->sub,
                ));
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
        }
    }

    private function decodeUserId(string $encodedUserId): string
    {
        $decoded = base64_decode($encodedUserId, strict: true);

        return $decoded !== false ? $decoded : '';
    }
}
