<?php

// parse auth0 get user data response, get user name and user initials
if (!function_exists('compareFormJson')) {
    function compareFormJson($responseJsonString, $submissionJsonString): string
    {
        if ($responseJsonString === $submissionJsonString) {
            return 'SUBMITTED';
        } else {
            return 'EDITED BY REVIEWER';
        }
    }
}
