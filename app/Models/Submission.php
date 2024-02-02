<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Submission extends Model
{
    //Database properties
    protected $connection = 'mongodb';
    protected $collection = 'submissions';

}
