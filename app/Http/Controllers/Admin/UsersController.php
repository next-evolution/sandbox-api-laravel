<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\UseCases\User\ApproveUserUseCase;
use App\UseCases\User\BlockUserUseCase;
use App\UseCases\User\GrantAdminUseCase;
use App\UseCases\User\SearchUsersUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function __construct(
        private readonly SearchUsersUseCase $searchUsersUseCase,
        private readonly ApproveUserUseCase $approveUserUseCase,
        private readonly BlockUserUseCase $blockUserUseCase,
        private readonly GrantAdminUseCase $grantAdminUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
            'emailAddress' => ['nullable', 'string'],
            'approved' => ['nullable', 'boolean'],
        ]);

        $approved = $request->input('approved');

        $result = $this->searchUsersUseCase->execute(
            emailAddress: $request->input('emailAddress'),
            approved: $approved === null ? null : (bool) $approved,
            page: (int) $request->input('page'),
            size: (int) $request->input('size'),
        );

        return response()->json([
            'returnCode' => 0,
            'totalCount' => $result['totalCount'],
            'searchCount' => $result['totalCount'],
            'totalPage' => $result['totalPage'],
            'list' => $result['list'],
        ]);
    }

    public function approved(Request $request, string $userIdBase64): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $userId = base64_decode($userIdBase64, strict: true) ?: '';

        $userDto = $this->approveUserUseCase->execute($userId, $authUser->sub);

        return response()->json([
            'returnCode' => 0,
            'user' => $userDto,
        ]);
    }

    public function block(Request $request, string $userIdBase64): JsonResponse
    {
        $request->validate([
            'blocked' => ['required', 'boolean'],
        ]);

        $authUser = $request->attributes->get('authUser');
        $userId = base64_decode($userIdBase64, strict: true) ?: '';

        $userDto = $this->blockUserUseCase->execute($userId, $request->boolean('blocked'), $authUser->sub);

        return response()->json([
            'returnCode' => 0,
            'user' => $userDto,
        ]);
    }

    public function grantAdmin(Request $request, string $userIdBase64): JsonResponse
    {
        $request->validate([
            'admin' => ['required', 'boolean'],
        ]);

        $authUser = $request->attributes->get('authUser');
        $userId = base64_decode($userIdBase64, strict: true) ?: '';

        $userDto = $this->grantAdminUseCase->execute($userId, $request->boolean('admin'), $authUser->sub);

        return response()->json([
            'returnCode' => 0,
            'user' => $userDto,
        ]);
    }
}
