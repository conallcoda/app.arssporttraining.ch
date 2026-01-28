<?php

namespace App\Exports;

use App\Models\Users\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserTrainingPlanExport implements WithMultipleSheets
{
    public function __construct(
        protected User $user,
        protected array $programPlans
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->programPlans as $plan) {
            $sheets[] = new ProgramTrainingPlanSheet(
                $this->user,
                $plan->programName,
                $plan->exercises
            );
        }

        return $sheets;
    }
}
