@php
    $trialPlan = $companyPlanStatus ?? \App\Services\CompanyPlanService::status(auth()->user()?->company);
@endphp
@if(($trialPlan['plan_type'] ?? null) === 'trial' && !empty($trialPlan['expires_at']))
@php
    $trialSeconds = max(0, (int) ceil(now()->diffInSeconds(\Carbon\Carbon::parse($trialPlan['expires_at']), false)));
@endphp
<style>
.company-trial-timer{display:flex;flex-direction:column;justify-content:center;flex-shrink:0;margin:0 14px 0 0;padding:7px 16px;border:1px solid #a5b4fc;border-radius:10px;background:linear-gradient(135deg,#5145cd,#3264cf);box-shadow:0 0 0 3px #818cf822,0 3px 12px #0003;color:#fff;line-height:1.3}
.company-trial-timer small{font-size:11px;font-weight:600;color:#eef2ff}.company-trial-timer strong{font-size:17px;font-weight:800;letter-spacing:.5px;font-variant-numeric:tabular-nums;white-space:nowrap}
.company-trial-timer.trial-urgent{background:#713f12;border-color:#fbbf24;box-shadow:0 0 0 3px #fbbf2422}.company-trial-timer.trial-expired{background:#881337;border-color:#fda4af}
@media(max-width:575px){.company-trial-timer{padding:5px 8px;margin-right:4px}.company-trial-timer strong{font-size:13px}.company-trial-timer small{font-size:10px}}
</style>
<div id="company-trial-timer" class="company-trial-timer" role="timer" aria-label="Trial time remaining" title="Trial expires {{ $trialPlan['expires_at_view'] }}">
    <small>Trial time remaining</small>
    <strong>{{ intdiv($trialSeconds, 86400) }}d {{ gmdate('H:i:s', $trialSeconds % 86400) }}</strong>
</div>
<script>
(function () {
    const timer = document.getElementById('company-trial-timer');
    const label = timer.querySelector('strong');
    const secondsAtLoad = @json($trialSeconds);
    const started = performance.now();
    let interval;
    function update() {
        const left = Math.max(0, secondsAtLoad - Math.floor((performance.now() - started) / 1000));
        timer.classList.toggle('trial-urgent', left > 0 && left <= 3 * 86400);
        timer.classList.toggle('trial-expired', left === 0);
        if (!left) {
            label.textContent = 'Trial expired';
            clearInterval(interval);
            return;
        }
        const pad = n => String(n).padStart(2, '0');
        label.textContent = Math.floor(left / 86400) + 'd ' + pad(Math.floor(left % 86400 / 3600)) + ':' + pad(Math.floor(left % 3600 / 60)) + ':' + pad(left % 60);
    }
    interval = setInterval(update, 1000);
    document.addEventListener('visibilitychange', update);
    update();
})();
</script>
@endif
