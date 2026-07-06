<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Exceptions\UpdateException;
use App\Models\SandboxUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BlockUserUseCase
{
    public function execute(string $userId, bool $blocked, string $updatedBy): array
    {
        $user = SandboxUser::where('user_id', $userId)->first();

        if ($user === null) {
            throw new NotFoundException('ユーザが存在しません');
        }

        $user->checkBlockDuplicate($blocked);

        DB::transaction(function () use ($user, $blocked, $updatedBy): void {
            $affected = SandboxUser::where('id', $user->id)->update([
                'blocked' => $blocked,
                'updated_at' => Carbon::now(),
                'updated_by' => $updatedBy,
            ]);
            if ($affected !== 1) {
                throw new UpdateException('Block設定');
            }
        });

        $user->refresh();

        return $user->toDtoArray();
    }
}
