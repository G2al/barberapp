<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiChatRequest;
use App\Services\Ai\AiRequestLogger;
use App\Services\Ai\OpenAiChatService;
use Illuminate\Http\JsonResponse;

class AiChatController extends Controller
{
    public function __invoke(
        AiChatRequest $request,
        OpenAiChatService $chatService,
        AiRequestLogger $requestLogger,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $chatService->ask($user, $request->validated('message'));
            $requestLogger->success($user, $result);

            $response = [
                'status' => true,
                'answer' => $result['answer'],
            ];

            if ($user->role === 'admin') {
                $response['usage'] = [
                    ...$result['usage'],
                    'estimated_cost_usd' => $result['estimated_cost_usd'],
                ];
            }

            return response()->json($response);
        } catch (AiServiceException $exception) {
            $requestLogger->failure(
                $user,
                (string) config('ai.model'),
                $exception->upstreamStatus ?? $exception->httpStatus,
                $exception->latencyMs,
                $exception->errorCode,
            );

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode,
            ], $exception->httpStatus);
        }
    }
}
