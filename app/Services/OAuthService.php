<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Predis\Client as PredisClient;

class OAuthService
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

    public function connectToRedis(): PredisClient
    {
        return new PredisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_MANAGED_HOST',''),
            'port' => env('REDIS_PORT',''),
            'client' => env('REDIS_MANAGED_USERNAME', ''),
            'username' => env('REDIS_MANAGED_USERNAME', ''),
            'password' => env('REDIS_MANAGED_PASSWORD', ''),
            'parameters' => [
                'username' => env('REDIS_MANAGED_USERNAME', ''),
                'password'=> env('REDIS_MANAGED_PASSWORD',''),
            ],
        ]);
    }

    private function getManagementApiToken() {
        $apiURL = "https://sciosystems.eu.auth0.com/oauth/token";

        $postData = [
            "grant_type" => "client_credentials",
            "client_id" => $this->clientID,
            "client_secret" => $this->clientSecret,
            "audience" => "https://sciosystems.eu.auth0.com/api/v2/"
        ];

        $response = Http::asForm()->post($apiURL, $postData);
        //TODO check response
        $responseBody = json_decode($response->getBody());
        Log::info("Management API Token Response", [$responseBody]);
        return $responseBody->access_token;
    }

    /*
    // Access token retrieval functions
    */

    public function getRoleId($userId)
    {
        $accessToken = $this->getManagementApiToken();

        $apiURL = "https://sciosystems.eu.auth0.com/api/v2/users/" . "auth0|" . $userId . "/roles";

        $headers = [
            "Authorization" => "Bearer " . $accessToken,
        ];

        $response = Http::withHeaders($headers)-> get($apiURL);
        //TODO check response
        $responseBody = json_decode($response->getBody());
        Log::info("User Role API Token Response", [$responseBody]);

        foreach($responseBody as $index=>$opt) {
            if (Str::startsWith($opt->name, "Era")) {
                return $opt->name;
            }
        }
        return "Era-None";
    }

    public function getUserData($userId)
    {
        $accessToken = $this->getManagementApiToken();

        $apiURL = "https://sciosystems.eu.auth0.com/api/v2/users/" . "auth0|" . $userId;

        $headers = [
            "Authorization" => "Bearer " . $accessToken,
        ];
        //TODO check response
        $response = Http::withHeaders($headers)-> get($apiURL);

        $responseBody = json_decode($response->getBody());
        Log::info("Get User API Token Response", [$responseBody->user_metadata]);
        return $responseBody;
    }

    //Use the refresh token to get a new access token
    public function refreshToken()
    {

        //Retrieve the header containing the user id
        try {
            $userId = $_SERVER[('HTTP_USER')];
        }
        catch (\Exception $e)
        {
            Log::error("Something went wrong with the User header: ", [$e]);
            return ["result" => "failed", "errorMessage" => "No user id was provided", "code" => 400];
        }

        Log::info("About to refresh the token for: ", [$userId]);
        //Get the user email and fetch the refresh token from redis
        $redisKey = "ERA-REFRESH-".$userId;
        $redisClient = $this->connectToRedis();
        $refreshToken = $redisClient->get($redisKey);
        Log::info("Refresh token from redis: ", [$refreshToken]);


        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://sciosystems.eu.auth0.com/oauth/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=refresh_token&client_id=".$this->clientID."&client_secret=".$this->clientSecret."&refresh_token=".$refreshToken,
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        //Get the status code from the token refresh
        $curlInfo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        Log::info("This is the info from the refresh ", [$curlInfo]);

        curl_close($curl);

        if ($err) {
            Log::error("Error while trying to refresh access token", [$err]);
            return ["result" => "failed","errorMessage" => 'Failed to refresh the access token', "code" => 500];
        }
        if($curlInfo != 200)
        {
            Log::info("Error while trying to refresh access token", [$response]);
            return ["result" => "failed","errorMessage" => 'Failed to refresh the access token', "code" => 401];
        }

        $json = json_decode($response);
        Log::info("First time handled ", [$json]);

        $redisClient = $this->connectToRedis();
        $redisResponse = $redisClient->set($redisKey, $json->refresh_token);
        Log::info("User refresh token updated: ", [$redisResponse, $redisKey, $json->refresh_token]);

        //Return the new access token
        return ["result" => "ok", "new_token" => $json->access_token, "code" => 200];
    }


    /*
    // Token validation functions
    */

    //Validate an access token given the permissions it should match
    public function auth0Validation($accessToken, $permission): array
    {

        // Now instantiate the Auth0 class with our configuration:
        $auth0 = new \Auth0\SDK\Auth0([
            'domain' => env('AUTH0_DOMAIN'),
            'clientId' => env('AUTH0_CLIENT_ID'),
            'clientSecret' => env('AUTH0_CLIENT_SECRET'),
            'audience' => [env('AUTH0_AUDIENCE'), "https://sciosystems.eu.auth0.com/userinfo"],
            'cookieSecret' => env('AUTH0_COOKIE_SECRET')
        ]);


        // Attempt to decode the token:
        try {
            Log::info("GOT THIS FAR IN AUTH0 VALIDATION");
            $token = $auth0->decode($accessToken);
            Log::info("this is the decoded one i think", [$token]);
            $permissionsToken = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $accessToken)[1]))));
        } catch (\Auth0\SDK\Exception\InvalidTokenException $exception) {
            // The token wasn't valid. Let's display the error message from the Auth0 SDK.
            // We'd probably want to show a custom error here for a real world application.
            Log::error("Could not validate the access token", [$exception->getMessage(), $exception]);
            if (str_contains($exception->getMessage(), 'exp'))
            {
                return ["result" => "failed", "errorMessage" => "Access token has expired", "code" => 401];
            }
            else{
                return  ["result" => "failed", "errorMessage" => "Could not validate the access token", "code" => 401];
            }
        }
        Log::info('Permissions array: ', $permissionsToken->permissions);
        //Check if the users permissions match the ones extracted from the token that was provided
        if(in_array( $permission ,$permissionsToken->permissions ))
        {
            return ["result" => "ok", "token" => $accessToken, "code" => 200];
        }
        else{
            return  ["result" => "failed", "errorMessage" => "User does not have the required permissions", "code" => 403];
        }
    }

    public function tokenValidation($permission): array
    {
        //Retrieve the header containing the access token
        try {
            $header = $_SERVER[('HTTP_AUTHORIZATION')];
        }
        catch (\Exception $e)
        {
            Log::error("Something went wrong with the Authorization header: ", [$e]);
            return ["result" => "failed", "errorMessage" => "No access token provided", "code" => 400];
        }
        $jwt = trim($header);

        // Remove the 'Bearer ' prefix, if present, in the event we're getting an Authorization header that's using it.
        if (substr($jwt, 0, 7) === 'Bearer ') {
            $jwt = substr($jwt, 7);
        }
        Log::info("This is the token: ", [$jwt]);

        $tokenValidateResult = $this->auth0Validation($jwt, $permission);
        if (strcmp($tokenValidateResult["result"],"failed") ==0)
        {
            if(str_contains($tokenValidateResult["errorMessage"], "Access token has expired"))
            {
                //Try to use the refresh token to create a new access token for the user
                $refreshAccessTokenResult = $this->refreshToken();
                Log::info("This is the result of the refresh ", [$refreshAccessTokenResult]);
                return $refreshAccessTokenResult;
            }
        }
        return $tokenValidateResult;

        /*// Now instantiate the Auth0 class with our configuration:
        $auth0 = new \Auth0\SDK\Auth0([
            'domain' => env('AUTH0_DOMAIN'),
            'clientId' => env('AUTH0_CLIENT_ID'),
            'clientSecret' => env('AUTH0_CLIENT_SECRET'),
            'audience' => [env('AUTH0_AUDIENCE'), "https://sciosystems.eu.auth0.com/userinfo"],
            'cookieSecret' => env('AUTH0_COOKIE_SECRET')
        ]);


        // Attempt to decode the token:
        try {
            Log::info("GOT THIS FAR");
            $token = $auth0->decode($jwt);
            Log::info("this is the decoded one i think", [$token]);
            $permissionsToken = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $jwt)[1]))));
        } catch (\Auth0\SDK\Exception\InvalidTokenException $exception) {
            // The token wasn't valid. Let's display the error message from the Auth0 SDK.
            // We'd probably want to show a custom error here for a real world application.
            Log::error("Could not validate the access token", [$exception->getMessage(), $exception]);
            if (str_contains($exception->getMessage(), 'exp'))
            {
                //Try to use the refresh token to create a new access token for the user
                $refreshAccessToken = $this->refreshToken();

                Log::info("This is the result of the refresh ", [$refreshAccessToken]);
                return ["result" => "failed", "errorMessage" => "Access token has expired", "code" => 401];

            }
            else{
                return  ["result" => "failed", "errorMessage" => "Could not validate the access token", "code" => 401];
            }
        }

        //Check if the users permissions match the ones extracted from the token that was provided
        if(in_array( $permission ,$permissionsToken->permissions ))
        {
            return ["result" => "ok", "token" => $jwt];
        }
        else{
            return  ["result" => "failed", "errorMessage" => "User does not have the required permissions", "code" => 403];
        }*/

    }



}
