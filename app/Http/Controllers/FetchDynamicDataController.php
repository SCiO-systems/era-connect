<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class FetchDynamicDataController extends Controller
{



    public function fetchExperimentalDesign($publicationId)
    {
        try
        {
            $experimentalDesign =
                DB::select('select * from experimental_design expD where expD.publication_id = :publicationId',
                            ['publicationId' => $publicationId]);

            //        remove unnecessary fields
            unset($experimentalDesign[0]->experimental_design_id);
            unset($experimentalDesign[0]->publication_id);
            return $experimentalDesign[0];
        }
        catch (\Exception $e)
        {
            return new \stdClass();
        }
    }

    public function fetchPublicationData($publicationCode): \stdClass
    {

//        object initialization: experimental design and publication info
        $data = new \stdClass();

        try
        {
            $publication =
                DB::select('select p.*, j.journal from publication p inner join journal j on j.journal_id = p.journal_id
                    where p.publication_code = :publicationCode',
                        ['publicationCode' => $publicationCode] );
            $publication[0]->publication_year =  $publication[0]->year_published;
            $experimentalDesign = $this->fetchExperimentalDesign($publication[0]->publication_id);

            unset($publication[0]->publication_id);
            unset($publication[0]->year_published);
            unset($publication[0]->journal_id);
            $data->publication = $publication[0];
            $data->experimental_design = $experimentalDesign;
        }
        catch (\Exception $e)
        {
            $data->publication = new \stdClass();
            $data->experimental_design = new \stdClass();
        }

        return $data;
    }




    public function fetchInfoByPubCode($publicationCode): \Illuminate\Http\JsonResponse
    {
        return response()->json(["response" => "ok", "data" => $this->fetchPublicationData($publicationCode)], 200);
    }

}
