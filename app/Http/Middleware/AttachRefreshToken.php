<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AttachRefreshToken
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $response = $next($request);
        if ($request->hasHeader('Refresh-Token')) {
            Log::info('Refresh-Token', [$request->header('Refresh-Token')]);
            $response->header('Access-Control-Expose-Headers', 'Refresh-Token');
            $response->header('Refresh-Token', $request->header('Refresh-Token'));
        }
        return $response;
    }
}
