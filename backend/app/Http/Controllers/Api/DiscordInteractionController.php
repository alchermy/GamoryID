<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Discord\DiscordCommandDispatcher;
use App\Services\Discord\DiscordSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordInteractionController extends Controller
{
    public function __invoke(Request $request, DiscordSignatureVerifier $verifier, DiscordCommandDispatcher $commands): JsonResponse
    {
        $body = $request->getContent();
        if (! $verifier->verify(
            $request->header('X-Signature-Ed25519'),
            $request->header('X-Signature-Timestamp'),
            $body,
        )) {
            return response()->json(['message' => 'ลายเซ็นคำขอไม่ถูกต้อง'], 401);
        }

        $interaction = $request->json()->all();
        if ((int) ($interaction['type'] ?? 0) === 1) {
            return response()->json(['type' => 1]);
        }

        return response()->json($commands->handle($interaction));
    }
}
