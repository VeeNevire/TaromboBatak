<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls a Hermes-style agent server that exposes a run/poll API:
 * submit a run, then poll it until it finishes or times out.
 *
 * Env vars (see docs/hermes-agent-berita-marga.md):
 *   HERMES_BASE_URL, HERMES_TOKEN, HERMES_RUNS_ENDPOINT,
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

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withToken((string) config('services.hermes.token'))
            ->acceptJson()
            ->timeout((int) config('services.hermes.timeout', 90));
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
        $response = $this->request()->post($endpoint, $input)->throw();
        $run = $response->json();
        $runId = $run['id'] ?? null;

        if (! is_array($run) || $runId === null) {
            throw new RuntimeException('Hermes tidak mengembalikan id run.');
        }

        $pollSeconds = max(1, (int) config('services.hermes.poll_seconds', 2));
        $deadline = now()->addSeconds((int) config('services.hermes.run_timeout', 300));

        while (! in_array($run['status'] ?? null, ['completed', 'failed', 'error'], true)) {
            if (now()->greaterThan($deadline)) {
                throw new RuntimeException("Run Hermes {$runId} melebihi batas waktu.");
            }

            sleep($pollSeconds);
            $run = $this->request()->get("{$endpoint}/{$runId}")->throw()->json();
        }

        if (($run['status'] ?? null) !== 'completed') {
            throw new RuntimeException("Run Hermes {$runId} gagal: ".($run['error'] ?? 'tidak diketahui'));
        }

        return $run;
    }
}
