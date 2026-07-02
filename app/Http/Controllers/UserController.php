<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\UseCases\User\GetProfileUseCase;
use App\UseCases\User\RegisterUserUseCase;
use App\UseCases\User\UpdateUserUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly GetProfileUseCase $getProfileUseCase,
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly UpdateUserUseCase $updateUserUseCase,
    ) {}

    public function profile(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');

        $userDto = $this->getProfileUseCase->execute($authUser->sub);

        if ($userDto === null) {
            return response()->json([
                'returnCode' => 1,
                'message'    => '利用承認待ちです',
                'user'       => null,
            ]);
        }

        return response()->json([
            'returnCode' => 0,
            'user'       => $userDto,
        ]);
    }

    public function registration(Request $request): JsonResponse
    {
        $request->validate([
            'nickName' => ['required', 'string', 'max:50'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $userDto = $this->registerUserUseCase->execute(
            userId:   $authUser->sub,
            email:    $authUser->email,
            nickName: $request->input('nickName'),
        );

        return response()->json([
            'returnCode' => 0,
            'user'       => $userDto,
        ]);
    }

    public function update(Request $request, string $userIdBase64): JsonResponse
    {
        $request->validate([
            'nickName' => ['required', 'string', 'max:50'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $userId = base64_decode($userIdBase64, strict: true) ?: '';
        if ($authUser->sub !== $userId) {
            throw new ForbiddenException('他のユーザの情報は更新できません');
        }

        $userDto = $this->updateUserUseCase->execute(
            userId:    $authUser->sub,
            nickName:  $request->input('nickName'),
            updatedBy: $authUser->sub,
        );

        return response()->json([
            'returnCode' => 0,
            'user'       => $userDto,
        ]);
    }
}
