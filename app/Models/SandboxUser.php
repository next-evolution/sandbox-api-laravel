<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateException;
use Illuminate\Database\Eloquent\Model;

class SandboxUser extends Model
{
    protected $table = 'sandbox_user';

    public $timestamps = false;

    protected $casts = [
        'approved'    => 'boolean',
        'admin'       => 'boolean',
        'blocked'     => 'boolean',
        'deleted'     => 'boolean',
        'approved_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // -------- ドメインメソッド --------

    public function isApproved(): bool
    {
        return (bool) $this->approved;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->admin;
    }

    public function checkBlocked(): void
    {
        if ($this->blocked) {
            throw new AuthenticationException('blocked.');
        }
    }

    public function checkAlreadyApproved(): void
    {
        if ($this->approved) {
            throw new DuplicateException('承認済みです');
        }
    }

    public function checkBlockDuplicate(bool $newBlocked): void
    {
        if ($this->blocked === $newBlocked) {
            throw new DuplicateException($newBlocked ? 'Block済みです' : 'Block解除済みです');
        }
    }

    public function checkAdminDuplicate(bool $newAdmin): void
    {
        if ($this->admin === $newAdmin) {
            throw new DuplicateException($newAdmin ? 'admin権限設定済みです' : 'admin権限設定剥奪済みです');
        }
    }

    public function toDtoArray(): array
    {
        return [
            'id'           => $this->id,
            'userId'       => $this->user_id,
            'emailAddress' => $this->email_address,
            'nickName'     => $this->nick_name,
            'approved'     => $this->approved,
            'approvedAt'   => $this->approved_at?->toIso8601String(),
            'admin'        => $this->admin,
            'blocked'      => $this->blocked,
            'createdAt'    => $this->created_at?->toIso8601String(),
            'updatedAt'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
