<?php

namespace App\Http\Controllers;

use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Filesystem;use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Predis\Client as PredisClient;

class FetchStaticDataController extends Controller
{

    public function connectToS3(): S3Client
    {
//             connect to s3 bucket
        return new S3Client([
            'credentials' => [
                'key'     =>  env('S3_KEY'),
                'secret'  => env('S3_SECRET'),
            ],
            'version' => 'latest',
            'region'  => 'eu-central-1',
        ]);
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

    public function getRData() {
        return Http::get(env('QVANTUM_URL'));
    }


//    check if specific key exists in redis
    public function idExistsInRedis($redisKey): string
    {
        $redisClient = $this->connectToRedis();
        $staticData = $redisClient->get($redisKey);

        if ($staticData == null)
        {
            return "";
        }
        else
        {
            return $staticData;
        }
    }


    public function fetchStaticJson(): \Illuminate\Http\JsonResponse
    {
        $redisClient = $this->connectToRedis();
        $s3client = $this->connectToS3();


        $redisKey = 'era_static_json';
        $staticData = $this->idExistsInRedis($redisKey);


//        redis key does not exist
        if ($staticData == "")
        {
            $adapter = new AwsS3Adapter($s3client, 'scio-era-dev');
            $filesystem = new Filesystem($adapter);
            $file = $filesystem->get('jsons_ui/lists_json.json');
            $staticJson = json_decode($file->getContent());

            $staticJson->crops = $this->fetchProductsSimple();
            $staticJson->trees = $this->fetchProducts();
            $staticJson->journals = $this->fetchJournals();
            $staticJson->countries =  $this->fetchCountries();

            $redisResponse = $redisClient->set(($redisKey), json_encode($staticJson));

            if (!$redisResponse)
            {
                return response()->json(["response" => "fail"], 500);
            }
            else
            {
                return response()->json(["response" => "ok", "static_data" => $staticJson], 200);
            }
        }
        else
        {
            return response()->json(["response" => "ok", "static_data" => json_decode($staticData)], 200);
        }
    }


    public function fetchProducts(): array
    {
        $treesArray = array();
        $trees = DB::select('select t.product_simple from trees t');

        foreach ($trees as $tree)
        {
            $valueObj = new \stdClass();
            $valueObj->value = $tree->product_simple;
            $treesArray[] = $valueObj;
        }

        return $treesArray;
    }


    public function fetchProductsSimple(): array
    {
        $productsArray = array();
        $products = DB::select('select distinct eu.product_simple from experimental_unit eu where
                                (eu.product_subtype = "Aggregated_Product" or eu.product_subtype = "Animal"
                                 or eu.product_subtype = "Cach Crops" or eu.product_subtype = "Cereals" or
                                 eu.product_subtype = "Fibre & Wood" or eu.product_subtype = "Fedders"  or
                                 eu.product_subtype = "Fruits"  or eu.product_subtype = "Legumes" or
                                 eu.product_subtype = "Nuts" or eu.product_subtype = "Spices" or
                                 eu.product_subtype = "Starchy Stapies" or eu.product_subtype = "Tree" or
                                 eu.product_subtype = "Vegetables")');

        foreach ($products as $product)
        {
            $valueObj = new \stdClass();
            $valueObj->value = $product->product_simple;
            $productsArray[] = $valueObj;
        }

        return $productsArray;
    }

    private function fetchJournals(): array
    {
        $journalsArray = array();
        $journals = DB::select('select j.journal from journal j');

        foreach ($journals as $journal) {
            $valueObj = new \stdClass();
            $valueObj->value = $journal->journal;
            $journalsArray[] = $valueObj;
        }

        return $journalsArray;
    }

    private function fetchCountries(): array
    {
        $countriesArray = array();
        $countries = DB::select('select c.country from country c');

        foreach ($countries as $country) {
            $valueObj = new \stdClass();
            $valueObj->value = $country->country;
            $countriesArray[] = $valueObj;
        }

        return $countriesArray;
    }


//    function for fetching practices based on the requested theme.
//    Check if subpractice is the requested field.
    public function fetchPracticesByTheme($theme): \Illuminate\Http\JsonResponse
    {
        try {
            $practicesArray = array();
            $practices = DB::select('select p.subpractice_s from practices p');

            foreach ($practices as $practice) {
                $practicesArray[] = $practice->subpractice_s;
            }

            return response()->json(["response" => "ok", "practices"=>$practicesArray], 200);
        }
        catch (\Exception $e)
        {
            return response()->json(["response" => "Error while loading practices for th requested theme."], 500);
        }
    }

}
