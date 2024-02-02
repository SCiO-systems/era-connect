<?php

namespace App\Http\Controllers;


use App\Models\Submission;
use App\Services\SubmissionService;
use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Extras\Resolvable\Resolver\AwsS3PublicUrlResolver;
use Gaufrette\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class SubmissionController extends Controller
{
    /*
    ////GET
    */
    //Get all user submissions from the collection        {user}
    public function getAllUserSubmissions($user_id)
    {
        $submissions = Submission::where('user.userId' , $user_id)
            ->where('deleted', false)
            ->where('status', '<>', 'EMPTY')
            ->project(['studyTitle' => 1, 'user' => 1, 'helper.submissionType' => 1, 'status' => 1, 'updated_at' => 1, 'submissionId' => 1])
            ->get();

        Log::info('Retrieving all user submissions ', [$user_id]);
        if($submissions == null)
        {
            Log::info('bad');
            $submissions = array();
        }
        Log::info($submissions);

        return response($submissions, 200);
    }

    //Get all submissions from the collection        {admin}
    public function getAllSubmissions()
    {
        $submissions = Submission::where('deleted', false)
            ->where('status', '<>', 'READY')
            ->where('status', '<>', 'DRAFT')
            ->where('status', '<>', 'EMPTY')
            ->project(['studyTitle' => 1, 'user' => 1, 'helper' => 1, 'status' => 1, 'updated_at' => 1, 'submissionId' => 1])
            ->get();

        Log::info('Retrieving all submissions ');
        if($submissions == null)
        {
            Log::info('bad');
            $submissions = array();
        }
        Log::info($submissions);

        return response($submissions, 200);
    }

    public function getSubmission($submission_id)
    {
        $submission = Submission::where('submissionId', $submission_id)
            ->first();
        Log::info('Retrieving submission with id: ', [$submission_id]);
        if($submission == null)
        {
            Log::info('bad');
        }
        Log::info('ok');

        return response($submission, 200);
    }

    /*
    ////POST
    */

    //New submission to the collection, given the userIds,status and formData    {user}
    public function newSubmission(Request $request, SubmissionService $submissionService)
    {
        $uuid = $submissionService->makeUuid();

        $submissionService->createSubmission($request, $uuid);

        return response()->json(["result" => "ok", "submissionId" => $uuid], 201);
    }

    public function uploadPdf(Request $request, SubmissionService $submissionService)
    {
        // write pdf to S3
        if ($request->hasFile('pdf')) {
            $submissionService->uploadPdf($request);
        }

        return response()->json(["result" => "ok"], 201);
    }

    /*
    ////PUT || PATCH
    */

    //Edit an existing submission with DRAFT || READY status
    public function editSubmission(Request $request, SubmissionService $submissionService)
    {
        $result = $submissionService->editSubmission($request);

        $submissionService->deleteEmptySubmissions($request->user_id);

        if ($result) {
            return response()->json(["result" => "ok"], 201);
        } else {
            return response()->json(["result" => "failed","errorMessage" => 'Requested submission not found'], 202);
        }
    }

    //Update the status of a submission from READY to SUBMITTED
    public function submitSubmission(Request $request, SubmissionService $submissionService)
    {
        $result = $submissionService->submit($request, "SUBMITTED");
        if ($result) {
            return response()->json(["result" => "ok"], 201);
        } else {
            return response()->json(["result" => "failed","errorMessage" => 'Requested submission not found'], 202);
        }
    }

    //Update the status of a submission from SUBMITTED to ACCEPTED
    public function approveSubmission(Request $request, SubmissionService $submissionService)
    {
        $result = $submissionService->approveOrReject($request, "APPROVED");
        if ($result) {
            return response()->json(["result" => "ok"], 201);
        } else {
            return response()->json(["result" => "failed","errorMessage" => 'Requested submission not found'], 202);
        }
    }

    //Update the status of a submission from SUBMITTED to ACCEPTED
    public function rejectSubmission(Request $request, SubmissionService $submissionService)
    {
        $result = $submissionService->approveOrReject($request, "REJECTED");
        if ($result) {
            return response()->json(["result" => "ok"], 201);
        } else {
            return response()->json(["result" => "failed","errorMessage" => 'Requested submission not found'], 202);
        }
    }

    /*
    ////DELETE
    */

    //Delete an submission with status DRAFT || READY                 {user, admin}
    public function deleteSubmission($submission_id, SubmissionService $submissionService)
    {
        $result = $submissionService->deleteSubmission($submission_id);
        if ($result) {
            return response()->json(["result" => "ok"], 201);
        } else {
            return response()->json(["result" => "failed","errorMessage" => 'Requested submission not found'], 202);
        }
    }

    public function checkSubmission($submission_id, SubmissionService $submissionService): \Illuminate\Http\JsonResponse
    {
        $result = $submissionService->searchSubmission($submission_id);
        Log::info($result);
        if ($result) {
            return response()->json(["result" => "ok"], 201);
        } else {
            return response()->json(["result" => "failed"], 202);
        }
    }

}
