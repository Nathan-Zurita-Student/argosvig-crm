<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\HumanResources\Contracts\IncidentImage as IncidentImageContract;

class IncidentImage extends Model implements IncidentImageContract
{
    protected $table = 'hr_incident_images';

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type'];
}
