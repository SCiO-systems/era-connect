<?php

namespace App\Http\Middleware;

use App\Services\OAuthService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EnsureTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * * @param  string  $permission
* //     * @return JsonResponse
     */

    public function handle(Request $request, Closure $next, string $permission)
    {
        $authResult = (new OAuthService)->tokenValidation($permission);
        if(strcmp($authResult["result"], "failed") == 0){
            Log::info("This is the auth result", $authResult);
            return response()->json(["result" => $authResult["result"], "errorMessage" => $authResult["errorMessage"]], $authResult["code"]);
        }
        elseif(array_key_exists("new_token", $authResult))
        {
            Log::info("This is the auth result", $authResult);
            $newAccessToken = $authResult["new_token"];
            $request->headers->set("Refresh-Token", $newAccessToken);
        } else {
            $request->headers->set("Refresh-Token", 0);
        }
        return $next($request);
    }
}
