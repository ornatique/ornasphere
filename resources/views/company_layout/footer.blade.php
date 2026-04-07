<footer class="footer">
  @php
    $companyName = optional(optional(auth()->user())->company)->name ?: config('app.name', 'OrnaSphere');
  @endphp
  <div class="d-sm-flex justify-content-center justify-content-sm-between">
    <span class="text-center text-sm-left d-block d-sm-inline-block">
      Copyright &copy; {{ now()->year }} <strong>{{ $companyName }}</strong>. All rights reserved.
    </span>
    <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">
      Company Panel
    </span>
  </div>
</footer>
