<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiChatRequest;
use App\Services\Ai\AiRequestLogger;
use App\Services\Ai\OpenAiChatService;
use App\Services\Ai\SalonContactAction;
use Illuminate\Http\JsonResponse;

class AiChatController extends Controller
{
    public function __invoke(
        AiChatRequest $request,
        OpenAiChatService $chatService,
        AiRequestLogger $requestLogger,
        SalonContactAction $contactAction,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $chatService->ask(
                $user,
                $request->validated('message'),
                $request->validated('history', []),
            );
            $requestLogger->success($user, $result);

            $response = [
                'status' => true,
                'answer' => $result['answer'],
            ];

            if ($result['action'] !== null) {
                $response['action'] = $result['action'];
            } elseif (str_contains(mb_strtolower($result['answer']), 'contatta il salone')) {
                if ($action = $contactAction->make()) {
                    $response['action'] = $action;
                }
            }

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

            $response = [
                'status' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode,
            ];

            if ($action = $contactAction->make()) {
                $response['action'] = $action;
            }

            return response()->json($response, $exception->httpStatus);
        }
    }
}
