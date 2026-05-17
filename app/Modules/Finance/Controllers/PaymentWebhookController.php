<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use App\Modules\Finance\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentGatewayInterface $paymentGateway,
        DepositService $depositService,
    ): JsonResponse {
        $signatureHeader = (string) config(
            'services.payment_provider.webhook_signature_header',
            'X-Webhook-Signature',
        );

        if (! $paymentGateway->verifyWebhookSignature($request->getContent(), $request->header($signatureHeader))) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $webhook = $paymentGateway->parseWebhook($request->all());
        $invoice = $depositService->handleWebhook($webhook);

        return response()->json([
            'status' => 'ok',
            'deposit_invoice_id' => $invoice->id,
            'credited' => $invoice->credited_at !== null,
        ]);
    }
}
