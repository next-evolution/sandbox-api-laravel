<?php

declare(strict_types=1);

namespace App\Models;

class AuthUser
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly bool $admin,
        public readonly bool $approved,
    ) {}

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function toArray(): array
    {
        return [
            'sub'           => $this->sub,
            'email'         => $this->email,
            'emailVerified' => $this->emailVerified,
            'admin'         => $this->admin,
            'approved'      => $this->approved,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sub:           $data['sub'],
            email:         $data['email'],
            emailVerified: (bool) $data['emailVerified'],
            admin:         (bool) $data['admin'],
            approved:      (bool) $data['approved'],
        );
    }
}
