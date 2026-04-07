<footer class="footer">
  @php
    $superAdminName = optional(auth('superadmin')->user())->name ?: 'Super Admin';
  @endphp
  <div class="d-sm-flex justify-content-center justify-content-sm-between">
    <span class="text-center text-sm-left d-block d-sm-inline-block">
      Copyright &copy; {{ now()->year }} <strong>{{ config('app.name', 'OrnaSphere') }}</strong>. All rights reserved.
    </span>
    <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">
      {{ $superAdminName }} Panel
    </span>
  </div>
</footer>
