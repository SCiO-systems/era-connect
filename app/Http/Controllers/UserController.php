<?php

namespace App\Http\Controllers;

use App\Services\OAuthService;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    /*
    //GET
    */

    //Retrieve existing user initials from auth0

    public function getUserInitials($userId, OAuthService $OAuthService) {
        //TODO check response
        $userInitials = getNameFromMetadata($OAuthService->getUserData($userId))->initials;
        return response()->json(["initials" => $userInitials]);
    }

}
