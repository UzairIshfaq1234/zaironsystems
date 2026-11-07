<?php

/*
| People rules: shifts, leave and salary conventions. Change the numbers here; the portal and payroll follow.
*/

return [

    // Minutes after the shift start before a check-in counts as late.
    'late_grace_minutes' => 15,

    // Days per month used to turn a monthly salary into a daily rate (unpaid leave is deducted per day).
    'days_per_month' => 30,

    /*
    | Leave types. `allowance` is the days each financial year; `carry_over` is how many unused days
    | move into the next year (annual leave only). `null` allowance = no limit (unpaid leave).
    */
    'leave_types' => [
        'annual' => ['label' => 'Annual leave', 'allowance' => 14, 'carry_over' => 30],
        'sick' => ['label' => 'Sick leave', 'allowance' => 8, 'carry_over' => 0],
        'casual' => ['label' => 'Casual leave', 'allowance' => 10, 'carry_over' => 0],
        'unpaid' => ['label' => 'Unpaid leave', 'allowance' => null, 'carry_over' => 0],
    ],

    /*
    | Monthly income tax on salaries (withheld by the company). Annual bands, marginal rates.
    | PLACEHOLDER VALUES: check them against the current Federal Board of Revenue schedule with your
    | accountant before relying on payslips. `up_to` null = no upper limit.
    */
    'tax_slabs' => [
        ['up_to' => 600000, 'rate' => 0.00],
        ['up_to' => 1200000, 'rate' => 0.01],
        ['up_to' => 2200000, 'rate' => 0.11],
        ['up_to' => 3200000, 'rate' => 0.23],
        ['up_to' => 4100000, 'rate' => 0.30],
        ['up_to' => null, 'rate' => 0.35],
    ],

    // Sign-in lockout for the employee portal, per email + IP.
    'portal_max_attempts' => 5,
];
