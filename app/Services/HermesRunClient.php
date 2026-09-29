<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Calls a Hermes-style agent server that exposes a run/poll API:
 * submit a run, then poll it until it finishes or times out.
 *
 * Env vars (see docs/hermes-agent-berita-marga.md):
 *   HERMES_BASE_URL, HERMES_TOKEN or API_SERVER_KEY, HERMES_RUNS_ENDPOINT,
 *   HERMES_TIMEOUT, HERMES_RUN_TIMEOUT, HERMES_POLL_SECONDS
 */
class HermesRunClient
{
    private function request(): PendingRequest
    {
        $baseUrl = config('services.hermes.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('HERMES_BASE_URL belum diisi.');
        }

        $token = config('services.hermes.token');

        $request = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->timeout((int) config('services.hermes.timeout', 90));

        if (filled($token)) {
            $request->withToken($token);
        }

        return $request;
    }

    /**
     * Starts a run and blocks until it finishes (polling) or the run timeout
     * is reached. $input is sent as-is in the run's body.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the finished run
     */
    public function runAndWait(array $input): array
    {
        $endpoint = (string) config('services.hermes.runs_endpoint', '/runs');
        $startedAt = microtime(true);
        $pollCount = 0;
        $baseUrl = (string) config('services.hermes.base_url');
        $target = $this->target($baseUrl, $endpoint);

        Log::info('Hermes run submission started.', [
            ...$target,
            'input_bytes' => strlen((string) ($input['input'] ?? '')),
        ]);

        $response = $this->send('POST', $endpoint, $input, 'submit', $target);
        $run = $response->json();

        if (! is_array($run)) {
            throw new RuntimeException('Hermes mengembalikan respons run yang tidak valid.');
        }

        $runId = $run['run_id'] ?? $run['id'] ?? null;

        if ($runId === null) {
            throw new RuntimeException('Hermes tidak mengembalikan run_id.');
        }

        Log::info('Hermes run accepted.', [
            ...$target,
            'run_id' => $runId,
            'status' => $run['status'] ?? 'unknown',
        ]);

        $pollSeconds = max(1, (int) config('services.hermes.poll_seconds', 2));
        $deadline = now()->addSeconds((int) config('services.hermes.run_timeout', 300));

        while (! in_array($run['status'] ?? null, ['completed', 'failed', 'error'], true)) {
            if (now()->greaterThan($deadline)) {
                throw new RuntimeException("Run Hermes {$runId} melebihi batas waktu.");
            }

            sleep($pollSeconds);
            $pollCount++;
            $pollEndpoint = rtrim($endpoint, '/').'/'.rawurlencode((string) $runId);
            $run = $this->send('GET', $pollEndpoint, null, 'poll', [
                ...$target,
                'run_id' => $runId,
            ])->json();

            if (! is_array($run)) {
                throw new RuntimeException("Hermes mengembalikan status run {$runId} yang tidak valid.");
            }

            if ($pollCount === 1 || $pollCount % 10 === 0) {
                Log::info('Hermes run is still processing.', [
                    ...$target,
                    'run_id' => $runId,
                    'status' => $run['status'] ?? 'unknown',
                    'poll_count' => $pollCount,
                    'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }
        }

        if (($run['status'] ?? null) !== 'completed') {
            throw new RuntimeException("Run Hermes {$runId} gagal: ".($run['error'] ?? 'tidak diketahui'));
        }

        Log::info('Hermes run completed.', [
            ...$target,
            'run_id' => $runId,
            'poll_count' => $pollCount,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'output_type' => get_debug_type($run['output'] ?? null),
        ]);

        return $run;
    }

    /** @param array<string, mixed> $context */
    private function send(string $method, string $endpoint, ?array $payload, string $stage, array $context): Response
    {
        $path = parse_url($endpoint, PHP_URL_PATH);
        $context['endpoint'] = is_string($path) ? $path : '/';

        try {
            $request = $this->request();
            $response = $method === 'POST'
                ? $request->post($endpoint, $payload ?? [])
                : $request->get($endpoint);
        } catch (Throwable $exception) {
            Log::error('Hermes HTTP request could not connect.', [
                ...$context,
                'stage' => $stage,
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 1500),
            ]);

            throw $exception;
        }

        if (! $response->successful()) {
            Log::error('Hermes HTTP request returned an error response.', [
                ...$context,
                'stage' => $stage,
                'http_status' => $response->status(),
                'response_bytes' => strlen($response->body()),
                'response_error' => $this->responseError($response),
            ]);

            $response->throw();
        }

        return $response;
    }

    /** @return array{target_host: string|null, target_port: int|null, endpoint: string} */
    private function target(string $baseUrl, string $endpoint): array
    {
        $path = parse_url($endpoint, PHP_URL_PATH);

        return [
            'target_host' => parse_url($baseUrl, PHP_URL_HOST) ?: null,
            'target_port' => parse_url($baseUrl, PHP_URL_PORT) ?: null,
            'endpoint' => is_string($path) ? $path : '/',
        ];
    }

    private function responseError(Response $response): ?string
    {
        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        foreach (['detail', 'error', 'message'] as $key) {
            if (is_string($body[$key] ?? null)) {
                return mb_substr($body[$key], 0, 1000);
            }
        }

        return null;
    }
}
