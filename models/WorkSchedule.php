<?php

final class WorkSchedule
{
    public const FULLTIME = 'FULLTIME';
    public const INTERN = 'INTERN';

    public static function isValidEmployeeType(string $employeeType): bool
    {
        return in_array($employeeType, [self::FULLTIME, self::INTERN], true);
    }

    public static function fixedSchedule(string $employeeType, string $date): ?array
    {
        if ($employeeType !== self::FULLTIME) {
            return null;
        }

        $workDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($workDate === false || $workDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Ngày làm việc không hợp lệ.');
        }
        if ((int) $workDate->format('N') > 5) {
            return null;
        }

        return [
            'shift_name' => 'Lịch Fulltime',
            'shift_start' => '09:00:00',
            'shift_end' => '17:00:00',
        ];
    }
}
