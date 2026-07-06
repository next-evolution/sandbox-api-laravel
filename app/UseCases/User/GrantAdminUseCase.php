<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Exceptions\UpdateException;
use App\Models\SandboxUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GrantAdminUseCase
{
    public function execute(string $userId, bool $admin, string $updatedBy): array
    {
        $user = SandboxUser::where('user_id', $userId)->first();

        if ($user === null) {
            throw new NotFoundException('ユーザが存在しません');
        }

        $user->checkAdminDuplicate($admin);

        DB::transaction(function () use ($user, $admin, $updatedBy): void {
            $affected = SandboxUser::where('id', $user->id)->update([
                'admin' => $admin,
                'updated_at' => Carbon::now(),
                'updated_by' => $updatedBy,
            ]);
            if ($affected !== 1) {
                throw new UpdateException('管理者権限設定');
            }
        });

        $user->refresh();

        return $user->toDtoArray();
    }
}
