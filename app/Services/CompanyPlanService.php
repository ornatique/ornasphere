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

        if ($company->parent_company_id) {
            $parent = Company::find($company->parent_company_id);
            if ($parent) return self::status($parent);
        }

        $startedAt = $company->getAttribute('plan_started_at')
            ? Carbon::parse($company->getAttribute('plan_started_at'))
            : ($company->created_at ? Carbon::parse($company->created_at) : now());

        $expiresAt = $company->getAttribute('plan_expires_at')
            ? Carbon::parse($company->getAttribute('plan_expires_at'))
            : ($company->plan === 'trial' ? $startedAt->copy()->addDays(10) : $startedAt->copy()->addYearNoOverflow());
        $now = now();
        $expired = $now->greaterThanOrEqualTo($expiresAt);
        $daysRemaining = $expired ? 0 : (int) ceil($now->diffInSeconds($expiresAt) / 86400);
        $plan = strtolower((string) ($company->plan ?: 'selected'));
        $alert = self::alert($plan, $expiresAt, $expired, $daysRemaining);

        return [
            'plan_type' => $plan,
            'trial_countdown' => [
                'show' => $plan === 'trial' && !$expired,
                'is_trial' => $plan === 'trial',
                'expired' => $expired,
                'server_time' => $now->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'remaining_seconds' => $expired ? 0 : (int) ceil($now->diffInSeconds($expiresAt)),
                'warning' => $plan === 'trial' && !$expired && $daysRemaining <= 3,
            ],
            'code' => $plan === 'trial' ? 'TRIAL_EXPIRED' : 'PLAN_EXPIRED',
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
        if ($plan === 'trial') {
            return ['show_popup'=>$expired || $daysRemaining <= 3, 'blocking'=>$expired, 'type'=>$expired ? 'expired' : 'trial', 'level'=>$expired ? 'expired' : 'warning', 'title'=>$expired ? 'Trial expired' : 'Trial expires in '.$daysRemaining.' day(s)', 'message'=>$expired ? 'Your 10-day trial has expired. Please contact your administrator to activate your subscription.' : 'Your trial expires on '.$expiresAt->format('d-m-Y h:i A').'. Contact your administrator to activate a one-year plan.'];
        }
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
                'title' => 'Plan expires within 24 hours',
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
