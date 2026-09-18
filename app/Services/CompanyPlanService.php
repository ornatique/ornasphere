<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;

class CompanyPlanService
{
    public static function status(?Company $company): array
    {
        if (!$company) {
            return [
                'started_at' => null,
                'started_at_view' => null,
                'expires_at' => null,
                'expires_at_view' => null,
                'expired' => false,
                'days_remaining' => null,
                'alert' => null,
            ];
        }

        $startedAt = $company->getAttribute('plan_started_at')
            ? Carbon::parse($company->getAttribute('plan_started_at'))
            : ($company->created_at ? Carbon::parse($company->created_at) : now());

        $expiresAt = $company->getAttribute('plan_expires_at')
            ? Carbon::parse($company->getAttribute('plan_expires_at'))
            : $startedAt->copy()->addYear();
        $now = now();
        $expired = $now->greaterThanOrEqualTo($expiresAt);
        $daysRemaining = $expired ? 0 : (int) ceil($now->diffInSeconds($expiresAt) / 86400);
        $plan = strtolower((string) ($company->plan ?: 'selected'));
        $alert = self::alert($plan, $expiresAt, $expired, $daysRemaining);

        return [
            'started_at' => $startedAt->toDateTimeString(),
            'started_at_view' => $startedAt->format('d-m-Y / h:i A'),
            'expires_at' => $expiresAt->toDateTimeString(),
            'expires_at_view' => $expiresAt->format('d-m-Y / h:i A'),
            'expired' => $expired,
            'days_remaining' => $daysRemaining,
            'alert' => $alert,
        ];
    }

    private static function alert(string $plan, Carbon $expiresAt, bool $expired, int $daysRemaining): array
    {
        $planName = $plan === 'selected' ? '' : ucfirst($plan) . ' ';
        $expiresAtView = $expiresAt->format('d-m-Y h:i A');

        if ($expired) {
            return [
                'show_popup' => true,
                'blocking' => true,
                'type' => 'expired',
                'level' => 'expired',
                'title' => 'Plan expired',
                'message' => 'Your ' . $planName . 'plan expired on ' . $expiresAtView . '. Please contact super admin to renew your plan.',
                'contact_name' => 'Super Admin',
                'contact_message' => 'Please contact super admin to renew your plan.',
            ];
        }

        if ($daysRemaining <= 1) {
            return [
                'show_popup' => true,
                'blocking' => false,
                'type' => 'expires_1_day',
                'level' => 'urgent',
                'title' => 'Plan expires tomorrow',
                'message' => 'Your ' . $planName . 'plan will expire on ' . $expiresAtView . '. Please renew your plan today.',
                'contact_name' => 'Super Admin',
                'contact_message' => 'Please contact super admin to renew your plan.',
            ];
        }

        if ($daysRemaining <= 7) {
            return [
                'show_popup' => true,
                'blocking' => false,
                'type' => 'expires_7_days',
                'level' => 'warning',
                'title' => 'Plan expires in ' . $daysRemaining . ' days',
                'message' => 'Your ' . $planName . 'plan will expire on ' . $expiresAtView . '. Please contact super admin to renew soon.',
                'contact_name' => 'Super Admin',
                'contact_message' => 'Please contact super admin to renew your plan.',
            ];
        }

        if ($daysRemaining <= 30) {
            return [
                'show_popup' => true,
                'blocking' => false,
                'type' => 'expires_30_days',
                'level' => 'notice',
                'title' => 'Plan expires in ' . $daysRemaining . ' days',
                'message' => 'Your ' . $planName . 'plan will expire on ' . $expiresAtView . '. Please renew before expiry.',
                'contact_name' => 'Super Admin',
                'contact_message' => 'Please contact super admin to renew your plan.',
            ];
        }

        return [
            'show_popup' => false,
            'blocking' => false,
            'type' => 'active',
            'level' => 'active',
            'title' => 'Plan active',
            'message' => 'Your ' . $planName . 'plan is active until ' . $expiresAtView . '.',
            'contact_name' => 'Super Admin',
            'contact_message' => 'Please contact super admin to renew your plan.',
        ];
    }
}
