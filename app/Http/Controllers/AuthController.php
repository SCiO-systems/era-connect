<?php

namespace App\Http\Controllers;

use App\Services\OAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{

    protected $audience;
    protected $clientID;
    protected $domain;
    protected $clientSecret;
    protected $redirectURI;
    protected $grantType;
    protected $redirectTo;
    protected $logoutRedirect;


    public function __construct()
    {
        $this->audience = env('AUTH0_AUDIENCE');
        $this->domain = env('AUTH0_DOMAIN');
        $this->clientID = env('AUTH0_CLIENT_ID');
        $this->clientSecret = env('AUTH0_CLIENT_SECRET');
        $this->redirectURI = env('AUTH0_REDIRECT_URL');
        $this->grantType = 'authorization_code';
        $this->redirectTo = env('APP_LOGIN_URL');
        $this->logoutRedirect = env('APP_LOGOUT_URL');
    }

    public function logoutUser(): \Illuminate\Http\RedirectResponse
    {

        $to = "https://" . $this->domain . "/v2/logout" . "?client_id=" . $this->clientID . "&returnTo=" . $this->logoutRedirect;
        Log::info($to);
        return redirect($to);
    }

    public function loginUser(): \Illuminate\Http\RedirectResponse
    {

        $to = "https://" . $this->domain . "/authorize?response_type=code&client_id=". $this->clientID . "&redirect_uri=" . $this->redirectURI . "&scope=openid%20email%20offline_access" . "&audience=" . $this->audience;
        Log::info("Redirecting to universal login: ", [$to]);
        return redirect($to);
    }

    public function validateUser(OAuthService $OAuthService): \Illuminate\Http\JsonResponse
    {
        $authResult = $OAuthService->tokenValidation("check:valid-token");
        Log::info('Checking token validity');
        if ($authResult["code"] == 200) {
            return response()->json(["result" => $authResult["result"]], $authResult["code"]);
        } else {
            return response()->json(["result" => $authResult["result"], "errorMessage" => $authResult["errorMessage"]], $authResult["code"]);
        }
    }

    //Authorize a user and get an access and refresh token
    public function callback(Request $request, OAuthService $OAuthService)
    {

        $apiURL = "https://sciosystems.eu.auth0.com/oauth/token";


        $postFields = [
            "grant_type" => "authorization_code",
            "client_id" => $this->clientID,
            "client_secret" => $this->clientSecret,
            "code" => $request->code,
            "redirect_uri" => $this->redirectURI,
            "scope" => "openid offline_access",
            // "audience" => $this->audience,

        ];

        $response = Http::asForm()->post($apiURL, $postFields);
        Log::info("response", [$response]);

        $json = json_decode($response->getBody());
        Log::info("First time handled ", [$json]);

        //Log::info("THIS IS THE ACCESS TOKEN", [$json->access_token]);
        //Log::info("This is the id token", [$json->id_token]);
        $idTemp = $json->access_token;
        $idArray = explode('.', $idTemp);
        $decodedIdToken = (json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',$idArray[1])))));
        Log::info("what about this", [$decodedIdToken]);

        //Get the user's email and id
        $idPieces = explode("|", $decodedIdToken->sub);
        $userId = $idPieces[1];
        $userRole = $OAuthService->getRoleId($userId);

        // Cache the refresh token in Redis using the email as key
        $redisKey = "ERA-REFRESH-".$userId;
        $redisClient = $OAuthService->connectToRedis();
        $redisResponse = $redisClient->set($redisKey, $json->refresh_token);
        Log::info("User refresh token added: ", [$redisResponse, $redisKey, $json->refresh_token]);

        $to = $this->redirectTo . '?' . http_build_query(['access_token' => $json->access_token, "user_id" => $userId, "role" => $userRole]);

        return redirect($to);

    }

}
