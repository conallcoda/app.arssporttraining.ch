<?php

namespace App\Models\Users\Types;

use App\Models\Users\User;
use Parental\HasParent;

class Coach extends User
{
    use HasParent;
}
