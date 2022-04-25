<?php

namespace Netflex\Actions\Middlewares;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $nonce = $request->headers->get('X-Nonce');
        $digest = $request->headers->get('X-Digest');
        $ts = $request->headers->get('X-Timestamp');
        $cTs = Carbon::parse($ts);

        $digestOk = $digest && hash_hmac('SHA256', '$ts$nonce', variable('netflex_api') === $digest);
        $timeOk = $cTs && abs($cTs->diffInSeconds(Carbon::now())) < 30;
        $notRunned = \Cache::get("run-$nonce", false) == false;

        if (($digestOk && $timeOk && $notRunned) || App::environment() === 'local') {
            Cache::set('run-{$nonce}', true);
            Log::debug('Authorized webhook', ['ts' => $ts, 'nonce' => $nonce, 'digest' => $digest]);
            return $next($request);
        }

        Log::debug('Unauthorized webhook', ['ts' => $ts, 'nonce' => $nonce, 'digest' => $digest]);
        return abort(401);
    }
}
