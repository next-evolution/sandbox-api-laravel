<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Exceptions\UpdateException;
use App\Models\SandboxUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateUserUseCase
{
    public function execute(string $userId, string $nickName, string $updatedBy): array
    {
        $user = SandboxUser::where('user_id', $userId)->first();

        if ($user === null) {
            throw new NotFoundException('ユーザが存在しません');
        }

        DB::transaction(function () use ($user, $nickName, $updatedBy): void {
            $affected = SandboxUser::where('id', $user->id)->update([
                'nick_name' => $nickName,
                'updated_at' => Carbon::now(),
                'updated_by' => $updatedBy,
            ]);
            if ($affected !== 1) {
                throw new UpdateException('ユーザ情報更新');
            }
        });

        $user->refresh();

        return $user->toDtoArray();
    }
}
