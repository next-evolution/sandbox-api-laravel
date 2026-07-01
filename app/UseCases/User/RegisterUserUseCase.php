<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\DuplicateException;
use App\Exceptions\InsertException;
use App\Models\SandboxUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterUserUseCase
{
    public function execute(string $userId, string $email, string $nickName): array
    {
        if (SandboxUser::where('user_id', $userId)->exists()) {
            throw new DuplicateException('登録済みのユーザです');
        }

        $now = Carbon::now();

        $user = new SandboxUser();
        $user->user_id       = $userId;
        $user->email_address = $email;
        $user->nick_name     = $nickName;
        $user->approved      = false;
        $user->approved_at   = null;
        $user->admin         = false;
        $user->blocked       = false;
        $user->deleted       = false;
        $user->created_at    = $now;
        $user->created_by    = $userId;
        $user->updated_at    = $now;
        $user->updated_by    = $userId;

        DB::transaction(function () use ($user): void {
            if (!$user->save()) {
                throw new InsertException('ユーザ新規登録');
            }
        });

        return $user->toDtoArray();
    }
}
