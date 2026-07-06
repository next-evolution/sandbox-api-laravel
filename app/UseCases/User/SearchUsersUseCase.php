<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\SandboxUser;

class SearchUsersUseCase
{
    public function execute(?string $emailAddress, ?bool $approved, int $page, int $size): array
    {
        $query = SandboxUser::where('deleted', false);

        if ($emailAddress !== null && $emailAddress !== '') {
            $query->where('email_address', 'like', "%{$emailAddress}%");
        }

        if ($approved !== null) {
            $query->where('approved', $approved);
        }

        $totalCount = $query->count();

        $list = $totalCount === 0
            ? []
            : $query->orderBy('id', 'desc')
                ->forPage($page, $size)
                ->get()
                ->map(fn (SandboxUser $u) => $u->toDtoArray())
                ->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
