<ol class="production-steps" aria-label="{{ $stepLabel }}">
@foreach($steps as $step)
<li class="{{ $loop->index < $currentStep ? 'is-complete' : ($loop->index === $currentStep ? 'is-current' : '') }}" @if($loop->index === $currentStep) aria-current="step" @endif>
<span>{{ $loop->index < $currentStep ? '✓' : $loop->iteration }}</span><strong>{{ $step }}</strong>
<small>{{ $loop->index < $currentStep ? 'Done' : ($loop->index === $currentStep ? 'Current step' : 'Next') }}</small>
</li>
@endforeach
</ol>
<style>
.production-steps{display:flex;flex-wrap:wrap;gap:12px;list-style:none;padding:0;margin:20px 0}.production-steps li{flex:1 1 150px;border:1px solid #7777;border-radius:8px;padding:14px}.production-steps span{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid currentColor;border-radius:50%;margin-right:8px}.production-steps small{display:block;margin-top:8px}.production-steps .is-current{border:2px solid #ff4081;background:#ff408110}.production-steps .is-complete{border-color:#26ad87}.production-next{border-left:4px solid #ff4081;padding:16px;background:#80808012;margin:16px 0}.production-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
</style>
