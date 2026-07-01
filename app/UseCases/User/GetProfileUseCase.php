<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Models\SandboxUser;

class GetProfileUseCase
{
    public function execute(string $userId): ?array
    {
        $user = SandboxUser::where('user_id', $userId)->first();

        if ($user === null) {
            throw new NotFoundException('ユーザが存在しません');
        }

        if (!$user->isApproved()) {
            return null;
        }

        return $user->toDtoArray();
    }
}
