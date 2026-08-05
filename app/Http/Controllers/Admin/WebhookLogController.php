<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WebhookLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'gateway' => ['nullable', 'in:selcom,azampay'],
            'result' => ['nullable', 'in:processed,rejected,error'],
        ]);

        $query = DB::table('payment_webhook_logs as logs')
            ->leftJoin('hotspot_transactions as transactions', function ($join) {
                $join->on('transactions.transaction_id', '=', 'logs.order_id')
                    ->on('transactions.payment_gateway', '=', 'logs.gateway');
            })
            ->select('logs.*', 'transactions.id as local_transaction_id', 'transactions.status as local_transaction_status');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search) {
                $query->where('logs.order_id', 'like', "%{$search}%")
                    ->orWhere('logs.gateway_transaction_id', 'like', "%{$search}%")
                    ->orWhere('logs.gateway_reference', 'like', "%{$search}%")
                    ->orWhere('logs.payment_status', 'like', "%{$search}%");
            });
        }

        if ($gateway = $filters['gateway'] ?? null) {
            $query->where('logs.gateway', $gateway);
        }

        match ($filters['result'] ?? null) {
            'processed' => $query->whereBetween('logs.response_status', [200, 299])->whereNull('logs.processing_error'),
            'rejected' => $query->whereBetween('logs.response_status', [400, 499]),
            'error' => $query->where(function ($query) {
                $query->whereNotNull('logs.processing_error')->orWhere('logs.response_status', '>=', 500);
            }),
            default => null,
        };

        $logs = $query->orderByDesc('logs.received_at')->paginate(25)->withQueryString();
        $logs->getCollection()->transform(function ($log) {
            $payload = json_decode((string) $log->payload, true);
            $log->sanitized_payload = json_encode($this->sanitizePayload(is_array($payload) ? $payload : []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $log;
        });

        return view('admin.webhook-logs', compact('logs', 'filters'));
    }

    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/password|secret|token|authorization|signature|api.?key/i', (string) $key)) {
                $payload[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            }
        }

        return $payload;
    }
}
