<?php

class SchedulePlanningEngine
{
    public static function intervalsOverlap(
        string $startA,
        string $endA,
        string $startB,
        string $endB
    ): bool {
        return $startA < $endB && $endA > $startB;
    }

    public function plan(
        string $fromDate,
        string $toDate,
        array $rules,
        array $employees,
        array $eventsByEmployee,
        array $leavesByEmployee,
        array $policy,
        ?array $priorityRules = null
    ): array {
        $priorityRules ??= [
            ['code' => 'QT010', 'priority_order' => 1],
            ['code' => 'QT009', 'priority_order' => 2],
            ['code' => 'QT012', 'priority_order' => 3],
        ];
        usort($priorityRules, static fn (array $left, array $right): int =>
            (int) $left['priority_order'] <=> (int) $right['priority_order']
        );
        $dates = $this->datesBetween($fromDate, $toDate);
        $assignments = [];
        $shortages = [];
        $issues = [];
        $coverage = [];

        foreach ($dates as $date) {
            foreach ($rules as $rule) {
                $existingCount = 0;
                foreach ($eventsByEmployee as $events) {
                    foreach ($events as $event) {
                        if (
                            ($event['source'] ?? '') === 'assignment'
                            && (int) $event['shift_id'] === (int) $rule['shift_id']
                            && $event['date'] === $date
                            && (int) $event['department_id'] === (int) $rule['department_id']
                        ) {
                            $existingCount++;
                        }
                    }
                }

                if ($existingCount > (int) $rule['max_staff']) {
                    $coverage[] = [
                        'date' => $date,
                        'requirement_id' => (int) $rule['id'],
                        'shift_id' => (int) $rule['shift_id'],
                        'shift_name' => $rule['shift_name'],
                        'department_id' => (int) $rule['department_id'],
                        'department' => $rule['department_name'],
                        'minimum' => (int) $rule['min_staff'],
                        'maximum' => (int) $rule['max_staff'],
                        'existing' => $existingCount,
                        'status' => 'over_capacity',
                    ];
                    $issues[] = [
                        'date' => $date,
                        'shift_id' => (int) $rule['shift_id'],
                        'department_id' => (int) $rule['department_id'],
                        'status' => 'over_capacity',
                        'message' => sprintf(
                            'QT008 · %s / %s đã có %d người, vượt mức tối đa %d.',
                            $rule['shift_name'],
                            $rule['department_name'],
                            $existingCount,
                            $rule['max_staff']
                        ),
                    ];
                    continue;
                }

                $remaining = max(0, (int) $rule['min_staff'] - $existingCount);
                $unassignedReasons = [];
                $blockedEmployees = [];
                foreach ($employees as $employee) {
                    if ((int) $employee['department_id'] !== (int) $rule['department_id']) {
                        $unassignedReasons['department'][(int) $employee['id']] = true;
                    }
                }
                for ($seat = 1; $seat <= $remaining; $seat++) {
                    $ranked = [];
                    foreach ($employees as $employee) {
                        if ((int) $employee['department_id'] !== (int) $rule['department_id']) {
                            continue;
                        }
                        $reasons = $this->candidateReasons(
                            $employee,
                            $rule,
                            $date,
                            $eventsByEmployee[(int) $employee['id']] ?? [],
                            $leavesByEmployee[(int) $employee['id']] ?? [],
                            $policy
                        );
                        if ($reasons !== []) {
                            foreach ($reasons as $reason) {
                                $unassignedReasons[$reason][(int) $employee['id']] = true;
                            }
                            $blockedEmployees[(int) $employee['id']] = [
                                'name' => $employee['name'],
                                'position' => $employee['position'],
                                'reasons' => array_values(array_unique(array_merge(
                                    $blockedEmployees[(int) $employee['id']]['reasons'] ?? [],
                                    $reasons
                                ))),
                            ];
                            continue;
                        }
                        $ranked[] = [
                            'employee' => $employee,
                            'priority' => $this->priority(
                                (int) $employee['id'],
                                $date,
                                $fromDate,
                                $toDate,
                                $rule,
                                $priorityRules,
                                $eventsByEmployee[(int) $employee['id']] ?? []
                            ),
                        ];
                    }

                    usort($ranked, static function (array $left, array $right): int {
                        for ($index = 0; $index < count($left['priority']); $index++) {
                            $comparison = $left['priority'][$index] <=> $right['priority'][$index];
                            if ($comparison !== 0) {
                                return $comparison;
                            }
                        }
                        return 0;
                    });

                    if ($ranked === []) {
                        continue;
                    }

                    $candidates = array_map(
                        static fn (array $item): array => [
                            'id' => (int) $item['employee']['id'],
                            'name' => $item['employee']['name'],
                            'position' => $item['employee']['position'],
                        ],
                        $ranked
                    );
                    $selected = $ranked[0]['employee'];
                    $event = $this->makeEvent($selected, $rule, $date);
                    $eventsByEmployee[(int) $selected['id']][] = $event;
                    $assignments[] = [
                        'slot_id' => $date . ':' . (int) $rule['id'] . ':' . $seat,
                        'requirement_id' => (int) $rule['id'],
                        'date' => $date,
                        'shift_id' => (int) $rule['shift_id'],
                        'shift_name' => $rule['shift_name'],
                        'start' => substr($rule['start'], 0, 5),
                        'end' => substr($rule['end'], 0, 5),
                        'department_id' => (int) $rule['department_id'],
                        'department' => $rule['department_name'],
                        'employee_id' => (int) $selected['id'],
                        'employee_name' => $selected['name'],
                        'position' => $selected['position'],
                        'location' => '',
                        'status' => 'assigned',
                        'candidates' => $candidates,
                    ];
                }

                $plannedCount = count(array_filter(
                    $assignments,
                    static fn (array $assignment): bool =>
                        $assignment['date'] === $date
                        && $assignment['requirement_id'] === (int) $rule['id']
                ));
                $missing = max(0, $remaining - $plannedCount);
                $coverage[] = [
                    'date' => $date,
                    'requirement_id' => (int) $rule['id'],
                    'shift_id' => (int) $rule['shift_id'],
                    'shift_name' => $rule['shift_name'],
                    'department_id' => (int) $rule['department_id'],
                    'department' => $rule['department_name'],
                    'minimum' => (int) $rule['min_staff'],
                    'maximum' => (int) $rule['max_staff'],
                    'existing' => $existingCount,
                    'planned' => $plannedCount,
                    'missing' => $missing,
                    'status' => $missing > 0 ? 'shortage' : 'covered',
                ];
                if ($missing > 0) {
                    $shortages[] = [
                        'date' => $date,
                        'shift_id' => (int) $rule['shift_id'],
                        'shift_name' => $rule['shift_name'],
                        'department_id' => (int) $rule['department_id'],
                        'department' => $rule['department_name'],
                        'minimum' => (int) $rule['min_staff'],
                        'existing' => $existingCount,
                        'planned' => $plannedCount,
                        'missing' => $missing,
                        'reasons' => array_map('count', $unassignedReasons),
                        'employees' => array_values($blockedEmployees),
                        'status' => $unassignedReasons === [] ? 'no_candidate' : 'conflict',
                    ];
                }
            }
        }

        return [
            'assignments' => $assignments,
            'shortages' => $shortages,
            'issues' => $issues,
            'coverage' => $coverage,
        ];
    }

    public function candidateReasons(
        array $employee,
        array $rule,
        string $date,
        array $events,
        array $leaves,
        array $policy
    ): array {
        if ((int) $employee['department_id'] !== (int) $rule['department_id']) {
            return ['department'];
        }
        $reasons = [];
        if ((int) $employee['active'] !== 1) {
            $reasons[] = 'inactive';
        }
        if (
            !empty($employee['termination_date'])
            && $date >= $employee['termination_date']
        ) {
            $reasons[] = 'terminated';
        }
        if (
            $rule['positions'] !== []
            && !in_array($employee['position'], $rule['positions'], true)
        ) {
            $reasons[] = 'position';
        }
        if (in_array($date . ':' . (int) $rule['shift_id'], $employee['blocked_slots'] ?? [], true)) {
            $reasons[] = 'previously_cancelled';
        }

        $candidate = $this->makeEvent($employee, $rule, $date);
        $maxConsecutiveNights = (int) ($policy['max_consecutive_nights'] ?? 0);
        if (
            $maxConsecutiveNights > 0
            && $this->isNightShift($candidate)
            && $this->consecutiveNightShiftsBefore($date, $events) >= $maxConsecutiveNights
        ) {
            $reasons[] = 'night_streak_limit';
        }
        foreach ($leaves as $leave) {
            if ($candidate['start_at'] < $leave['end_at'] && $candidate['end_at'] > $leave['start_at']) {
                $reasons[] = 'approved_leave';
                break;
            }
        }

        $restMinutes = (int) ($policy['min_rest_minutes'] ?? 0);
        foreach ($events as $event) {
            if (self::intervalsOverlap(
                $candidate['start_at'],
                $candidate['end_at'],
                $event['start_at'],
                $event['end_at']
            )) {
                $reasons[] = 'schedule_conflict';
                break;
            }
            if ($restMinutes > 0) {
                $gapMinutes = $candidate['start_at'] >= $event['end_at']
                    ? $this->minutesBetween($event['end_at'], $candidate['start_at'])
                    : ($event['start_at'] >= $candidate['end_at']
                        ? $this->minutesBetween($candidate['end_at'], $event['start_at'])
                        : PHP_INT_MAX);
                if ($gapMinutes < $restMinutes) {
                    $reasons[] = 'insufficient_rest';
                    break;
                }
            }
        }

        $maxDaily = $policy['max_daily_minutes'] ?? null;
        if ($maxDaily !== null) {
            $dailyMinutes = $candidate['minutes'];
            foreach ($events as $event) {
                if ($event['date'] === $date) {
                    $dailyMinutes += (int) $event['minutes'];
                }
            }
            if ($dailyMinutes > (int) $maxDaily) {
                $reasons[] = 'daily_limit';
            }
        }

        $maxWeekly = $policy['max_weekly_minutes'] ?? null;
        if ($maxWeekly !== null) {
            $week = $this->weekKey($date);
            $weeklyMinutes = $candidate['minutes'];
            foreach ($events as $event) {
                if ($this->weekKey($event['date']) === $week) {
                    $weeklyMinutes += (int) $event['minutes'];
                }
            }
            if ($weeklyMinutes > (int) $maxWeekly) {
                $reasons[] = 'weekly_limit';
            }
        }

        return array_values(array_unique($reasons));
    }

    private function priority(
        int $employeeId,
        string $date,
        string $fromDate,
        string $toDate,
        array $rule,
        array $priorityRules,
        array $events
    ): array {
        $week = $this->weekKey($date);
        $weeklyMinutes = 0;
        $weeklyShifts = 0;
        $periodMinutes = 0;
        $periodShifts = 0;
        foreach ($events as $event) {
            if ($event['date'] >= $fromDate && $event['date'] <= $toDate) {
                $periodMinutes += (int) $event['minutes'];
                if (($event['source'] ?? '') === 'assignment') {
                    $periodShifts++;
                }
            }
            if ($this->weekKey($event['date']) === $week) {
                $weeklyMinutes += (int) $event['minutes'];
                if (($event['source'] ?? '') === 'assignment') {
                    $weeklyShifts++;
                }
            }
        }

        $candidate = $this->makeEvent(
            ['id' => $employeeId, 'department_id' => (int) $rule['department_id']],
            $rule,
            $date
        );
        $metrics = [
            'QT009' => [$periodShifts, $weeklyShifts],
            'QT010' => [$weeklyMinutes, $periodMinutes],
            'QT012' => [
                $this->isNightShift($candidate)
                    ? $this->consecutiveNightShiftsBefore($date, $events)
                    : 0,
            ],
        ];
        $priority = [];
        foreach ($priorityRules as $priorityRule) {
            foreach ($metrics[$priorityRule['code']] ?? [] as $metric) {
                $priority[] = $metric;
            }
        }
        $priority[] = $employeeId;
        return $priority;
    }

    private function isNightShift(array $event): bool
    {
        if (($event['source'] ?? 'assignment') !== 'assignment') {
            return false;
        }
        $start = (int) date('G', strtotime($event['start_at']));
        $end = (int) date('G', strtotime($event['end_at']));
        return $end <= $start || $start >= 20 || $end < 6;
    }

    private function consecutiveNightShiftsBefore(string $date, array $events): int
    {
        $nightDates = [];
        foreach ($events as $event) {
            if ($this->isNightShift($event)) {
                $nightDates[$event['date']] = true;
            }
        }
        $count = 0;
        $previousDate = (new DateTimeImmutable($date))->modify('-1 day');
        while (isset($nightDates[$previousDate->format('Y-m-d')])) {
            $count++;
            $previousDate = $previousDate->modify('-1 day');
        }
        return $count;
    }

    private function makeEvent(array $employee, array $rule, string $date): array
    {
        $start = new DateTimeImmutable($date . ' ' . $rule['start']);
        $end = new DateTimeImmutable($date . ' ' . $rule['end']);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        $minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
        if (!empty($rule['break_start']) && !empty($rule['break_end'])) {
            $breakStart = new DateTimeImmutable($date . ' ' . $rule['break_start']);
            $breakEnd = new DateTimeImmutable($date . ' ' . $rule['break_end']);
            if ($breakStart < $start) {
                $breakStart = $breakStart->modify('+1 day');
            }
            if ($breakEnd <= $breakStart) {
                $breakEnd = $breakEnd->modify('+1 day');
            }
            if ($breakStart >= $start && $breakEnd <= $end) {
                $minutes -= (int) (($breakEnd->getTimestamp() - $breakStart->getTimestamp()) / 60);
            }
        }

        return [
            'employee_id' => (int) $employee['id'],
            'department_id' => (int) $employee['department_id'],
            'shift_id' => (int) $rule['shift_id'],
            'date' => $date,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s'),
            'minutes' => max(0, $minutes),
            'source' => 'assignment',
        ];
    }

    private function datesBetween(string $fromDate, string $toDate): array
    {
        $dates = [];
        $date = new DateTimeImmutable($fromDate);
        $end = new DateTimeImmutable($toDate);
        while ($date <= $end) {
            $dates[] = $date->format('Y-m-d');
            $date = $date->modify('+1 day');
        }
        return $dates;
    }

    private function weekKey(string $date): string
    {
        return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
    }

    private function minutesBetween(string $start, string $end): int
    {
        return max(0, (int) ((strtotime($end) - strtotime($start)) / 60));
    }
}
