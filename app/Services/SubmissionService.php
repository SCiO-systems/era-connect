<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\CustomTerm;
use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubmissionService
{
    private function connectToS3(): S3Client
    {
//             connect to s3 bucket
        return new S3Client([
            'credentials' => [
                'key'     =>  env('S3_KEY'),
                'secret'  => env('S3_SECRET'),
            ],
            'version' => 'latest',
            'region'  => env('S3_REGION'),
        ]);
    }


    public function uploadPdf($request): ?bool
    {
        $s3client = $this->connectToS3();
        $adapter = new AwsS3Adapter($s3client, env('S3_BUCKET'));
        $filesystem = new Filesystem($adapter);
        if ($filesystem->has("uploadedPdfs/" . $request->submission_id)) {
            $filesystem->delete("uploadedPdfs/" . $request->submission_id);
        }
        $filesystem->write("uploadedPdfs/" . $request->submission_id, file_get_contents($request->pdf));
        $file = $filesystem->has("uploadedPdfs/" . $request->submission_id);
        if ($file) {
            Log::info('Successfully added pdf file to S3 bucket with filename: ' . $request->submission_id);
            return true;
        } else {
            Log::warning('Unable to upload file' . $request->submission_id);
            return false;
        }
    }

    public function deleteEmptySubmissions($user_id): void
    {
        $submissions = Submission::where('user.userId' , $user_id)
            ->where('status' , 'EMPTY')
            ->where('deleted', false)
            ->get();
        Log::info($submissions);
        foreach ($submissions as $sub) {
            $sub->deleted = true;
            $sub->save();
//            $sub->delete();
        }
    }

    public function createSubmission(Request $request, $uuid): void
    {
        $submission = new Submission;
        $currentTime = round(microtime(true) * 1000);

        $submission->submissionId = $uuid;
        $submission->status = 'EMPTY';                       //DRAFT || READY
        $submission->deleted = false;                                 //True for soft deleted submissions
        $userData = getNameFromMetadata((new OAuthService())->getUserData($request->user_id));
        $submission->user = (object)array("userId"=>$request->user_id, "userName" => $userData->fullName);
        $submission->save();

        Log::info('Adding new submission with id: ', [$submission->submissionId, $submission ]);
    }

    public function makeUuid(): string
    {
        return 'ERA-'.Str::uuid()->toString();
    }

    public function searchSubmission($submissionId): bool
    {
        $submission = Submission::where('submissionId', $submissionId)
            ->where('deleted', false)
            ->first();
        Log::info('Submission found: ', [$submission]);
        if ($submission == null) {
            return false;
        } else {
            return true;
        }
    }

    public function editSubmission(Request $request): bool
    {
        $submission = Submission::where('submissionId', $request->submission_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "EMPTY")->
                orWhere('status', "READY")->
                orWhere('status', "DRAFT")->
                orWhere('status', "SUBMITTED")->
                orWhere('status', "REJECTED")->
                orWhere('status', "REVIEWER DRAFT")->
                orWhere('status', "EDITED BY REVIEWER");
            })
            ->first();

        //Check for null and submission status
        if($submission ==null)
        {
            Log::warning('Requested submission not found', [$request->submission_id]);
            return false;
        }

        $this->processEdit($submission, $request);

        //Save, log and response
        $submission->save();
        Log::info('Updating submission', [$submission]);
        return true;
    }

    private function processEdit(Submission $submission, Request $request) {
        $editMode = $request->edit_by;
        $status = $request->status;

        if ($editMode === 'reviewer') {
            $newFormData = json_decode($request->form_data, false)->studyData;
            $newFormData = json_encode($newFormData);
            $oldFormData = json_encode($submission->studyData);

            $status = $this->compareFormData($newFormData, $oldFormData, $status);
//            $customTermsArray = ;
//            callToJava($customTermsArray);
        }
        $this->parseEditSubmissionJson($submission, $request, $editMode);
        $submission->status = $status;
    }

    private function compareFormData($newData, $oldData, $status) {
        if ($status === 'DRAFT') {
            return 'DRAFT';
        }
        if ($status === 'REVIEWER DRAFT') {
            return 'REVIEWER DRAFT';
        }
        if ($status === 'EDITED BY REVIEWER') {
            return 'EDITED BY REVIEWER';
        } else {
            if ($newData === $oldData) {
                return 'SUBMITTED';
            } else {
                return 'EDITED BY REVIEWER';
            }
        }
    }

    private function parseEditSubmissionJson($submission, $request, $mode) {
        $formData = json_decode($request->form_data, false);
        $review = $formData->review;
        if ($mode === 'reviewer') {
            $review->reviewerId = $request->user_id;
        }
        $submission->studyTitle = $request->study_title;
        $submission->studyData = $formData->studyData;
        $submission->review = $review;
        $submission->helper = $formData->helper;
    }

    public function submit($request, $targetStatus): bool {
        //Fetch the submission from the database (latest version for safety)

        $submission = Submission::where('submissionId', $request->submission_id)
            ->where('deleted', false)
            ->where('status', 'READY')
            ->first();

        //Check for null and submission status
        if($submission ==null)
        {
            Log::warning('Requested submission not found', [$request->submission_id]);
            return false;
        }
        $review = (object) $submission->review;
        $review->underReview = true;
        $submission->review = $review;
        $submission->status = $targetStatus;

        $submission->save();
        Log::info('Submitting submission', [$submission]);
        return true;
    }

    public function approveOrReject($request, $targetStatus): bool {
        //Fetch the submission from the database (latest version for safety)
        $submission = Submission::where('submissionId', $request->submission_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', 'SUBMITTED')
                    ->orWhere('status', 'EDITED BY REVIEWER');
            })

            ->first();

        //Check for null and submission status
        if($submission ==null)
        {
            Log::warning('Requested submission not found', [$request->submission_id]);
            return false;
        }

        $review = (object) $submission->review;
        $review->reviewerId = $request->user_id;
        $review->underReview = false;
        $submission->review = $review;
        $submission->status = $targetStatus;

        Log::info($targetStatus);
        if ($targetStatus == 'APPROVED') {
            $this->addCustomTerms($request->submission_id, $submission);
        }

        $submission->save();
        Log::info('Submitting submission', [$submission]);
        return true;
    }

    public function deleteSubmission($submission_id): bool {
        $submission = Submission::where('submissionId', $submission_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "DRAFT")->
                orWhere('status', "READY");
            })
            ->first();

        //Check for null and submission status
        if($submission ==null)
        {
            Log::warning('Requested submission not found', [$submission_id]);
            return false;
        }

        Log::info('Deleting submission', [$submission]);
        $submission->deleted = true;
        $submission->save();
        return true;
    }

    public function addCustomTerms($submission_id, $submission): void {
        $review = $submission->review;
        if (property_exists($review, 'approvedTerms')) {
            $approvedTerms = $review->approvedTerms;
            unset($approvedTerms['dataType']);
            if (is_array($approvedTerms)) {
                foreach ($approvedTerms as $t) {
                    $customTerm = new CustomTerm;
                    $customTerm->term = $t['term'];
                    $customTerm->vocabulary = $t['vocabulary'];
                    $customTerm->studyId = $submission_id;
                    if (array_key_exists('parentValue', $t )) {
                        $customTerm->parentValue = $t['parentValue'];
                    }
                    Log::info($customTerm);
                    $customTerm->save();
                }
            }
        }
    }
}
