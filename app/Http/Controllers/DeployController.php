<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Finishes a deploy that CI could not finish itself.
 *
 * Namecheap's shared hosting gives GitHub Actions no way to run artisan after
 * the files land, so this does the last mile over HTTP: migrate, then rebuild
 * the caches that had to be skipped at build time.
 *
 * That is a great deal of power to hang off a URL, so:
 *
 *   - it does not exist unless `DEPLOY_HOOK_TOKEN` is set, and a wrong or
 *     missing token 404s rather than 403s — an attacker learns nothing about
 *     whether the route is even there;
 *   - the comparison is constant-time, so the token cannot be guessed a
 *     character at a time;
 *   - it is throttled, and every call is logged either way.
 *
 * If the host offers SSH or cPanel Terminal, run the commands there instead and
 * leave the token unset.
 */
class DeployController extends Controller
{
    /**
     * Commands safe to run unattended after an upload.
     *
     * `config:cache` is here rather than in CI on purpose: caching config on
     * the build runner would bake that runner's empty `.env` into the artefact
     * and override the real one on the server.
     */
    private const COMMANDS = [
        'migrate' => ['--force' => true],
        'config:cache' => [],
        'route:cache' => [],
        'view:cache' => [],
        'storage:link' => [],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $results = [];

        foreach (self::COMMANDS as $command => $arguments) {
            try {
                $exitCode = Artisan::call($command, $arguments);

                $results[$command] = [
                    'exit_code' => $exitCode,
                    'output' => trim(Artisan::output()),
                ];
            } catch (\Throwable $e) {
                // Report rather than abort: a failed `storage:link` (the link
                // usually already exists) should not hide whether `migrate`
                // succeeded, which is the one that matters.
                $results[$command] = [
                    'exit_code' => 1,
                    'output' => $e->getMessage(),
                ];
            }
        }

        $failed = array_keys(array_filter($results, fn ($result) => $result['exit_code'] !== 0));

        Log::info('Deploy hook ran.', ['failed' => $failed]);

        return response()->json([
            'ok' => $failed === [],
            'failed' => $failed,
            'results' => $results,
        ], $failed === [] ? 200 : 500);
    }

    /**
     * A missing token means the feature is off; a wrong one means somebody is
     * poking at it. Both get the same 404.
     */
    private function authorizeRequest(Request $request): void
    {
        $expected = (string) config('deploy.token');

        if ($expected === '') {
            throw new NotFoundHttpException;
        }

        $provided = (string) $request->header('X-Deploy-Token', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Deploy hook rejected a request.', ['ip' => $request->ip()]);

            throw new NotFoundHttpException;
        }
    }
}
