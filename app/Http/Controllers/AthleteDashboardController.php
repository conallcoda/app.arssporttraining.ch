<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AthleteDashboardController
{
    public function calendarLegacy(Request $request)
    {
        return redirect()->route('athlete.dashboard.train', [
            'date' => $request->query('date'),
        ]);
    }

    public function calendarDay(?string $date = null)
    {
        return redirect()->route('athlete.dashboard.train', array_filter([
            'date' => $date,
        ]));
    }

    public function calendarWeek(?string $date = null)
    {
        return redirect()->route('athlete.dashboard.schedule', array_filter([
            'date' => $date !== null
                ? CarbonImmutable::parse($date)->startOfWeek()->format('Y-m-d')
                : null,
        ]));
    }

    public function calendarUnrecorded()
    {
        return redirect()->route('athlete.dashboard.unrecorded');
    }
}
