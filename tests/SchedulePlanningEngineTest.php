<?php

require_once __DIR__ . '/../models/SchedulePlanningEngine.php';

function expectSchedule(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$engine = new SchedulePlanningEngine();
$rule = [
    'id' => 1,
    'shift_id' => 10,
    'shift_name' => 'Ca sáng',
    'department_id' => 2,
    'department_name' => 'Bán hàng',
    'min_staff' => 1,
    'max_staff' => 2,
    'positions' => ['Nhân viên bán hàng'],
    'start' => '08:00:00',
    'end' => '12:00:00',
    'break_start' => null,
    'break_end' => null,
];
$employeeOne = [
    'id' => 1,
    'name' => 'Nhân viên Một',
    'position' => 'Nhân viên bán hàng',
    'department_id' => 2,
    'active' => 1,
    'termination_date' => null,
];
$employeeTwo = [
    'id' => 2,
    'name' => 'Nhân viên Hai',
    'position' => 'Nhân viên bán hàng',
    'department_id' => 2,
    'active' => 1,
    'termination_date' => null,
];
$date = '2026-12-01';
$priorHours = [
    1 => [[
        'source' => 'assignment',
        'date' => $date,
        'start_at' => $date . ' 13:00:00',
        'end_at' => $date . ' 18:00:00',
        'minutes' => 300,
        'shift_id' => 11,
        'department_id' => 2,
    ]],
    2 => [[
        'source' => 'assignment',
        'date' => $date,
        'start_at' => $date . ' 13:00:00',
        'end_at' => $date . ' 15:00:00',
        'minutes' => 120,
        'shift_id' => 12,
        'department_id' => 2,
    ]],
];
$plan = $engine->plan(
    $date,
    $date,
    [$rule],
    [$employeeOne, $employeeTwo],
    $priorHours,
    [],
    ['max_daily_minutes' => null, 'max_weekly_minutes' => null, 'min_rest_minutes' => 0]
);
expectSchedule(count($plan['assignments']) === 1, 'Expected one preview assignment.');
expectSchedule($plan['assignments'][0]['employee_id'] === 2, 'Expected lower-hour employee to be preferred.');

$previousDate = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
$boundaryHours = [
    1 => [[
        'source' => 'approved_overtime',
        'date' => $date,
        'start_at' => $date . ' 14:00:00',
        'end_at' => $date . ' 15:00:00',
        'minutes' => 60,
        'shift_id' => null,
        'department_id' => 2,
    ]],
    2 => [[
        'source' => 'approved_overtime',
        'date' => $previousDate,
        'start_at' => $previousDate . ' 14:00:00',
        'end_at' => $previousDate . ' 15:00:00',
        'minutes' => 60,
        'shift_id' => null,
        'department_id' => 2,
    ]],
];
$boundaryPlan = $engine->plan(
    $date,
    $date,
    [$rule],
    [$employeeOne, $employeeTwo],
    $boundaryHours,
    [],
    ['max_daily_minutes' => null, 'max_weekly_minutes' => null, 'min_rest_minutes' => 0]
);
expectSchedule(
    $boundaryPlan['assignments'][0]['employee_id'] === 2,
    'Hours just outside the selected period must not affect period fairness.'
);

$preferenceEvents = [
    1 => [[
        'source' => 'assignment',
        'date' => $date,
        'start_at' => $date . ' 14:00:00',
        'end_at' => $date . ' 15:00:00',
        'minutes' => 60,
        'shift_id' => 11,
        'department_id' => 2,
    ]],
    2 => [[
        'source' => 'overtime',
        'date' => $date,
        'start_at' => $date . ' 14:00:00',
        'end_at' => $date . ' 16:00:00',
        'minutes' => 120,
        'shift_id' => null,
        'department_id' => 2,
    ]],
];
$fairnessFirstPlan = $engine->plan(
    $date,
    $date,
    [$rule],
    [$employeeOne, $employeeTwo],
    $preferenceEvents,
    [],
    ['max_daily_minutes' => null, 'max_weekly_minutes' => null, 'min_rest_minutes' => 0],
    [
        ['code' => 'QT009', 'priority_order' => 1],
        ['code' => 'QT010', 'priority_order' => 2],
    ]
);
expectSchedule(
    $fairnessFirstPlan['assignments'][0]['employee_id'] === 2,
    'Configured preference order must prioritize fairness before work hours.'
);
$hoursFirstPlan = $engine->plan(
    $date,
    $date,
    [$rule],
    [$employeeOne, $employeeTwo],
    $preferenceEvents,
    [],
    ['max_daily_minutes' => null, 'max_weekly_minutes' => null, 'min_rest_minutes' => 0],
    [
        ['code' => 'QT010', 'priority_order' => 1],
        ['code' => 'QT009', 'priority_order' => 2],
    ]
);
expectSchedule(
    $hoursFirstPlan['assignments'][0]['employee_id'] === 1,
    'Configured preference order must allow work-hour balancing to take precedence.'
);

$wrongPosition = $employeeOne;
$wrongPosition['position'] = 'Kế toán viên';
expectSchedule(
    in_array('position', $engine->candidateReasons($wrongPosition, $rule, $date, [], [], []), true),
    'A disallowed position must not be assigned.'
);
$wrongDepartment = $employeeOne;
$wrongDepartment['department_id'] = 3;
expectSchedule(
    $engine->candidateReasons($wrongDepartment, $rule, $date, [], [], []) === ['department'],
    'A department mismatch must be reported as a QT002 exclusion.'
);

$leave = [['start_at' => $date . ' 07:00:00', 'end_at' => $date . ' 09:00:00']];
expectSchedule(
    in_array('approved_leave', $engine->candidateReasons($employeeOne, $rule, $date, [], $leave, []), true),
    'Approved leave overlapping a shift must block assignment.'
);

$overlap = [[
    'source' => 'assignment',
    'date' => $date,
    'start_at' => $date . ' 10:00:00',
    'end_at' => $date . ' 14:00:00',
    'minutes' => 240,
    'shift_id' => 12,
    'department_id' => 2,
]];
expectSchedule(
    in_array('schedule_conflict', $engine->candidateReasons($employeeOne, $rule, $date, $overlap, [], []), true),
    'Overlapping assignments must be blocked.'
);
expectSchedule(
    in_array(
        'insufficient_rest',
        $engine->candidateReasons($employeeOne, $rule, $date, $priorHours[1], [], ['min_rest_minutes' => 180]),
        true
    ),
    'Insufficient rest between shifts must be blocked.'
);
expectSchedule(
    in_array(
        'daily_limit',
        $engine->candidateReasons($employeeOne, $rule, $date, $priorHours[1], [], ['max_daily_minutes' => 480]),
        true
    ),
    'Daily work-hour limits must be enforced.'
);
expectSchedule(
    in_array(
        'weekly_limit',
        $engine->candidateReasons($employeeOne, $rule, $date, $priorHours[1], [], ['max_weekly_minutes' => 500]),
        true
    ),
    'Weekly work-hour limits must be enforced.'
);

$nightRule = $rule;
$nightRule['start'] = '22:00:00';
$nightRule['end'] = '06:00:00';
$previousNight = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
$secondPreviousNight = (new DateTimeImmutable($date))->modify('-2 days')->format('Y-m-d');
$nightEvents = [
    [
        'source' => 'assignment',
        'date' => $previousNight,
        'start_at' => $previousNight . ' 22:00:00',
        'end_at' => $date . ' 06:00:00',
        'minutes' => 480,
        'shift_id' => 10,
        'department_id' => 2,
    ],
    [
        'source' => 'assignment',
        'date' => $secondPreviousNight,
        'start_at' => $secondPreviousNight . ' 22:00:00',
        'end_at' => $previousNight . ' 06:00:00',
        'minutes' => 480,
        'shift_id' => 10,
        'department_id' => 2,
    ],
];
expectSchedule(
    in_array(
        'night_streak_limit',
        $engine->candidateReasons(
            $employeeOne,
            $nightRule,
            $date,
            $nightEvents,
            [],
            ['max_consecutive_nights' => 2]
        ),
        true
    ),
    'An overnight shift must not exceed the configured consecutive-night limit.'
);

echo "Schedule planning engine tests passed.\n";
