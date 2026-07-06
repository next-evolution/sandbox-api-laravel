<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Exceptions\UpdateException;
use App\Models\SandboxUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApproveUserUseCase
{
    public function execute(string $userId, string $updatedBy): array
    {
        $user = SandboxUser::where('user_id', $userId)->first();

        if ($user === null) {
            throw new NotFoundException('ユーザが存在しません');
        }

        $user->checkAlreadyApproved();

        $now = Carbon::now();

        DB::transaction(function () use ($user, $now, $updatedBy): void {
            $affected = SandboxUser::where('id', $user->id)->update([
                'approved' => true,
                'approved_at' => $now,
                'updated_at' => $now,
                'updated_by' => $updatedBy,
            ]);
            if ($affected !== 1) {
                throw new UpdateException('ユーザ承認');
            }
        });

        $user->refresh();

        return $user->toDtoArray();
    }
}
