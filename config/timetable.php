<?php

return [
    /*
    |--------------------------------------------------------------------
    | Max periods per week (feasibility threshold)
    |--------------------------------------------------------------------
    |
    | Used by the Timetable Feasibility Report (T1b) to flag teachers
    | placed for more periods than a normal working week should hold.
    |
    */
    'max_periods_per_week' => env('TIMETABLE_MAX_PERIODS_PER_WEEK', 36),
];
