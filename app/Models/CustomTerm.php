<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class CustomTerm extends Model
{
    //Database properties
    protected $connection = 'mongodb';
    protected $collection = 'custom-terms';

}
