<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Exceptions\AuthenticationException;
use App\Models\AuthUser;
use App\Models\SandboxUser;
use App\Services\SessionService;

class LoginUseCase
{
    public function __construct(
        private readonly SessionService $sessionService,
    ) {}

    public function execute(AuthUser $authUser, string $encodedEmail): ?array
    {
        $decodedEmail = $this->decodeEmail($encodedEmail);

        if ($authUser->email !== $decodedEmail) {
            throw new AuthenticationException('login failed.');
        }

        $user = SandboxUser::where('user_id', $authUser->sub)
            ->where('email_address', $decodedEmail)
            ->first();

        $user?->checkBlocked();

        $authUserWithFlags = new AuthUser(
            sub: $authUser->sub,
            email: $authUser->email,
            emailVerified: $authUser->emailVerified,
            admin: $user?->admin ?? false,
            approved: $user?->approved ?? false,
        );
        $this->sessionService->save($authUserWithFlags);

        return $user?->toDtoArray();
    }

    private function decodeEmail(string $encodedEmail): string
    {
        $decoded = base64_decode($encodedEmail, strict: true);
        if ($decoded === false) {
            throw new AuthenticationException('Invalid BASE64 email');
        }

        return $decoded;
    }
}
