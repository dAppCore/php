<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Webhook;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController
{
    public function handle(Request $request, string $source): JsonResponse
    {
        $signatureValid = null;

        // Check for registered verifier
        $verifier = app()->bound("webhook.verifier.{$source}")
            ? app("webhook.verifier.{$source}")
            : null;

        if ($verifier instanceof WebhookVerifier) {
            $secret = config("webhook.secrets.{$source}", '');
            $signatureValid = $verifier->verify($request, $secret);
        }

        // Extract event type from common payload patterns
        $payload = $request->json()->all();
        $eventType = $payload['type'] ?? $payload['event_type'] ?? $payload['event'] ?? null;

        $call = WebhookCall::create([
            'source' => $source,
            'event_type' => is_string($eventType) ? $eventType : null,
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'signature_valid' => $signatureValid,
        ]);

        event(new WebhookReceived($source, $call->id));

        return response()->json(['ok' => true]);
    }
}
