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

    /*
    |--------------------------------------------------------------------
    | Auto-generation solver (T4a)
    |--------------------------------------------------------------------
    |
    | time_budget_seconds: wall-clock cap on GeneratorService::generate();
    | it returns the best state found so far once exceeded, per the plan's
    | "hard time budget" requirement.
    |
    | backtrack_budget_per_lesson: how many local-relocation attempts the
    | solver makes for a single lesson with zero legal slots before giving
    | up on it and marking it UNPLACED -- bounds the "depth-limited...
    | backtracking on dead ends" requirement so a hard-to-place lesson
    | can't stall the whole run.
    |
    */
    'generator' => [
        'time_budget_seconds' => env('TIMETABLE_GENERATOR_TIME_BUDGET', 60),
        'backtrack_budget_per_lesson' => env('TIMETABLE_GENERATOR_BACKTRACK_BUDGET', 25),

        // Whole-school Generate: GenerateTimetableJob's own timeout, separate
        // from time_budget_seconds above -- this is headroom for the DB work
        // around the solve (delete old drafts, insert every placement row),
        // not the solver's own search budget.
        'job_timeout_seconds' => env('TIMETABLE_GENERATOR_JOB_TIMEOUT', 300),
    ],
];
