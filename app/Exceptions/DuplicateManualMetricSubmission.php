<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateManualMetricSubmission extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('There is already a manual metric for this day. Please edit the existing one.');
    }
}
