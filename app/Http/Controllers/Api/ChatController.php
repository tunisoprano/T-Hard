<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatService;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
    ) {}

    public function respond(SendChatMessageRequest $request)
    {
        $data = $request->validated();

        $user = User::findOrFail($data['user_id']);
        $store = isset($data['store_id']) ? Store::findOrFail($data['store_id']) : null;

        // Controller sadece isteği alır, işlemi tamamen Servise devreder.
        $assistantMessage = $this->chatService->processMessage($user, $store, $data['message']);

        return response()->json(ChatMessageResource::make($assistantMessage));
    }
}
