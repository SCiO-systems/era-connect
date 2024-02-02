<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class VocabulariesController extends Controller
{
    private function connectToRedis(): PredisClient
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

    public function fetchVocabularies()
    {
        $redisClient = $this->connectToRedis();
        $vocabulariesFlat = json_decode($redisClient->get("ERA-VOCABULARIES"),1);
        $vocabulariesRelational = json_decode($redisClient->get("ERA-VOCABULARIES-RELATIONAL"), 1);
        $vocabularies = array_merge($vocabulariesFlat ,$vocabulariesRelational);

        return response()->json($vocabularies, 200);
    }

    public function fetchFields()
    {
        $redisClient = $this->connectToRedis();
        $vocabularies = json_decode($redisClient->get("ERA-VOCABULARIES-FIELDS"));

        return response()->json($vocabularies, 200);
    }

}
