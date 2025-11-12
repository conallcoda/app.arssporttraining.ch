<?php

namespace App\Models\Users\Groups;

use App\Models\Users\UserGroup;
use Parental\HasParent;

class AthleteGroup extends UserGroup
{
    use HasParent;
}
