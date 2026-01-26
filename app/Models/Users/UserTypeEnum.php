<?php

namespace App\Models\Users;

enum UserTypeEnum: string
{
    case Coach = 'coach';
    case Athlete = 'athlete';
    case Admin = 'admin';
}
