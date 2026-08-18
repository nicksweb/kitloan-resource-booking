<?php

namespace App\Services\Booking;

use App\Models\ApprovalRule;
use App\Models\BookingType;
use App\Models\ResourcePool;
use App\Settings\SettingsRepository;
use Carbon\CarbonInterface;

class ApprovalEvaluator
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Hard constraints — violating any of these blocks submission outright
     * (not an approval question). Returns an array of user-facing error
     * messages; empty means the window is legal to submit.
     *
     * @return array<int, string>
     */
    public function validateWindow(ResourcePool $pool, CarbonInterface $start, CarbonInterface $end): array
    {
        $errors = [];

        if ($end->lessThanOrEqualTo($start)) {
            $errors[] = 'Finish time must be after the start time.';
        }

        if ($start->diffInMinutes(now(), false) > -$pool->minimum_lead_time_minutes) {
            $errors[] = sprintf(
                'This resource pool requires at least %s notice.',
                $this->formatMinutes($pool->minimum_lead_time_minutes)
            );
        }

        if (($start->isWeekend() || $end->isWeekend()) && ! $pool->allow_weekends) {
            $errors[] = 'Weekend bookings are not permitted for this resource pool.';
        }

        if (! $pool->allow_out_of_hours && $this->isOutsideSchoolHours($start, $end)) {
            $errors[] = sprintf(
                'This resource pool only allows bookings between %s and %s.',
                $this->settings->get('school_day_start', '07:00'),
                $this->settings->get('school_day_finish', '17:00')
            );
        }

        return $errors;
    }

    /**
     * Soft constraints — booking is allowed but needs manual approval.
     * Returns the list of reasons (empty = eligible for auto-approval).
     *
     * @return array<int, string>
     */
    public function reasonsRequiringApproval(
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?BookingType $bookingType,
        int $totalQuantity,
    ): array {
        $reasons = [];

        if (! $pool->auto_approval_enabled) {
            $reasons[] = 'This resource pool requires manual approval for every booking.';
        }

        if ($bookingType?->requires_approval) {
            $reasons[] = sprintf('"%s" bookings always require manual approval.', $bookingType->name);
        }

        $leadHours = (int) $this->settings->get('min_auto_approval_lead_hours', 6);
        if (now()->diffInMinutes($start, false) < $leadHours * 60) {
            $reasons[] = sprintf('Less than %d hours notice.', $leadHours);
        }

        if (($start->isWeekend() || $end->isWeekend()) && $this->settings->get('weekend_requires_approval', true)) {
            $reasons[] = 'Weekend bookings require approval.';
        }

        if ($this->isOutsideSchoolHours($start, $end) && $this->settings->get('out_of_hours_requires_approval', true)) {
            $reasons[] = 'Outside normal school hours.';
        }

        $applicableRules = ApprovalRule::query()
            ->enabled()
            ->where('rule_type', 'min_quantity')
            ->where(fn ($q) => $q->whereNull('resource_pool_id')->orWhere('resource_pool_id', $pool->id))
            ->get();

        foreach ($applicableRules as $rule) {
            if ($totalQuantity >= $rule->threshold_value) {
                $reasons[] = sprintf('%s (%d or more resources).', $rule->name, $rule->threshold_value);
            }
        }

        return $reasons;
    }

    private function isOutsideSchoolHours(CarbonInterface $start, CarbonInterface $end): bool
    {
        $dayStart = $this->settings->get('school_day_start', '07:00');
        $dayFinish = $this->settings->get('school_day_finish', '17:00');

        return $start->format('H:i') < $dayStart || $end->format('H:i') > $dayFinish;
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $remainder === 0 ? "{$hours} hours" : "{$hours}h {$remainder}m";
    }
}
