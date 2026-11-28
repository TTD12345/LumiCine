<?php

require_once __DIR__ . '/../models/WorkSchedule.php';

function expectWorkSchedule(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expectWorkSchedule(
    WorkSchedule::fixedSchedule(WorkSchedule::FULLTIME, '2026-11-30') === [
        'shift_name' => 'Lịch Fulltime',
        'shift_start' => '09:00:00',
        'shift_end' => '17:00:00',
    ],
    'A Fulltime employee must receive the fixed schedule on Monday.'
);
expectWorkSchedule(
    WorkSchedule::fixedSchedule(WorkSchedule::FULLTIME, '2026-12-04') !== null,
    'A Fulltime employee must receive the fixed schedule on Friday.'
);
expectWorkSchedule(
    WorkSchedule::fixedSchedule(WorkSchedule::FULLTIME, '2026-12-05') === null,
    'A Fulltime employee must not receive the default schedule on Saturday.'
);
expectWorkSchedule(
    WorkSchedule::fixedSchedule(WorkSchedule::FULLTIME, '2026-12-06') === null,
    'A Fulltime employee must not receive the default schedule on Sunday.'
);
expectWorkSchedule(
    WorkSchedule::fixedSchedule(WorkSchedule::INTERN, '2026-11-30') === null,
    'An Intern must never fall back to the Fulltime schedule.'
);

echo "Work schedule tests passed.\n";
