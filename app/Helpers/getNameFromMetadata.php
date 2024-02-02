<?php

// parse auth0 get user data response, get user name and user initials
if (!function_exists('getNameFromMetadata')) {
    function getNameFromMetadata($responseJson): object
    {
        $userMetadata = $responseJson->user_metadata;
        $userFullName = $userMetadata->name . " " . $userMetadata->surname;
        $userInitials = preg_filter('/[^A-Z]/', '', $userFullName);
        return (object) ['initials' => $userInitials, 'fullName' => $userFullName];
    }
}
