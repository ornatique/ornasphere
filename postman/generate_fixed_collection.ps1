$ErrorActionPreference = 'Stop'
$inPath = 'C:\Users\Admin\Downloads\ornasphere.postman_collection.json'
$outDir = 'C:\wamp64\www\ornasphere\postman'
$outPath = Join-Path $outDir 'ornasphere.postman_collection.fixed.json'

New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$collection = Get-Content $inPath -Raw | ConvertFrom-Json
$routes = php artisan route:list --path=api --json | ConvertFrom-Json

function Flatten-Items($items) {
  $out = @()
  foreach ($it in $items) {
    if ($it.item) { $out += Flatten-Items $it.item }
    elseif ($it.request) { $out += $it }
  }
  return $out
}

$existing = Flatten-Items $collection.item

function UrlToPath($urlObj) {
  if ($urlObj -is [string]) {
    return ([System.Uri]$urlObj).AbsolutePath.TrimStart('/')
  }
  if ($urlObj.raw) {
    return ([System.Uri]$urlObj.raw).AbsolutePath.TrimStart('/')
  }
  return ''
}

function BuildRegexFromRoute($routeUri) {
  $rx = [regex]::Escape($routeUri)
  $rx = $rx -replace '\\\{[^\\\}]+\\\}', '[^/]+'
  return '^' + $rx + '$'
}

function SamplePathFromRoute($routeUri) {
  return ($routeUri -replace '\{[^}]+\}', '1')
}

$existingByRouteKey = @{}

foreach ($r in $routes) {
  $methods = @($r.method -split '\|') | Where-Object { $_ -ne 'HEAD' }
  $rx = BuildRegexFromRoute $r.uri
  foreach ($m in $methods) {
    $match = $existing | Where-Object {
      $_.request.method -eq $m -and ((UrlToPath $_.request.url) -match $rx)
    } | Select-Object -First 1

    if ($match) {
      $existingByRouteKey["$m $($r.uri)"] = $match
    }
  }
}

function JsonBody($obj) { return ($obj | ConvertTo-Json -Depth 20) }

$bodyTemplates = @{}
$bodyTemplates['POST api/company/login'] = JsonBody @{ email='{{email}}'; password='{{password}}' }
$bodyTemplates['POST api/company/verify-otp'] = JsonBody @{ user_id='{{userId}}'; otp='{{otp}}' }
$bodyTemplates['POST api/customers'] = JsonBody @{ name='Postman Customer'; email='customer@example.com'; mobile_no='9999999999'; city='Ahmedabad'; is_active=$true }
$bodyTemplates['PUT api/customers/{id}'] = JsonBody @{ city='Surat'; mobile_no='8888888888'; is_active=$true }
$bodyTemplates['POST api/create_customers'] = JsonBody @{ name='Legacy Customer'; email='legacy.customer@example.com'; mobile_no='9090909090'; city='Ahmedabad'; is_active=$true }
$bodyTemplates['POST api/update_customers/{id}'] = JsonBody @{ city='Vadodara'; mobile_no='7777777777' }

$newItems = @()

foreach ($r in $routes) {
  $methods = @($r.method -split '\|') | Where-Object { $_ -ne 'HEAD' }
  foreach ($m in $methods) {
    $key = "$m $($r.uri)"
    $source = $existingByRouteKey[$key]

    if ($source) {
      $req = $source.request
      # normalize auth headers to variables while preserving API link
      $headers = @()
      $hasAccept = $false
      $hasAuth = $false
      foreach ($h in $req.header) {
        if ($h.key -eq 'Accept') { $hasAccept = $true; $h.value = 'application/json' }
        if ($h.key -eq 'Authorization') { $hasAuth = $true; $h.value = 'Bearer {{token}}' }
        $headers += $h
      }
      if (-not $hasAccept) {
        $headers += [pscustomobject]@{ key='Accept'; value='application/json'; type='text' }
      }
      if (($r.middleware -contains 'App\Http\Middleware\Authenticate:sanctum') -and -not $hasAuth) {
        $headers += [pscustomobject]@{ key='Authorization'; value='Bearer {{token}}'; type='text' }
      }
      $req.header = $headers

      # ensure JSON content-type for body methods
      if (@('POST','PUT','PATCH') -contains $m) {
        $hasCt = ($req.header | Where-Object { $_.key -eq 'Content-Type' }).Count -gt 0
        if (-not $hasCt) {
          $req.header += [pscustomobject]@{ key='Content-Type'; value='application/json'; type='text' }
        }
        if (-not $req.body) {
          $raw = if ($bodyTemplates.ContainsKey($key)) { $bodyTemplates[$key] } else { '{}' }
          $req | Add-Member -Name body -Value ([pscustomobject]@{ mode='raw'; raw=$raw; options=[pscustomobject]@{ raw=[pscustomobject]@{ language='json' } } }) -MemberType NoteProperty -Force
        }
      }

      $item = [pscustomobject]@{ name = "https://ornasphere.com/$($r.uri)"; request = $req; response = @() }
      $newItems += $item
      continue
    }

    # create missing request
    $samplePath = SamplePathFromRoute $r.uri
    $rawUrl = "https://ornasphere.com/$samplePath"

    $headers = @([pscustomobject]@{ key='Accept'; value='application/json'; type='text' })
    if ($r.middleware -contains 'App\Http\Middleware\Authenticate:sanctum') {
      $headers += [pscustomobject]@{ key='Authorization'; value='Bearer {{token}}'; type='text' }
    }

    $url = [pscustomobject]@{
      raw = $rawUrl
      protocol = 'https'
      host = @('ornasphere','com')
      path = ($samplePath -split '/')
    }

    $request = [pscustomobject]@{ method = $m; header = $headers; url = $url }

    if (@('POST','PUT','PATCH') -contains $m) {
      $request.header += [pscustomobject]@{ key='Content-Type'; value='application/json'; type='text' }
      $raw = if ($bodyTemplates.ContainsKey($key)) { $bodyTemplates[$key] } else { '{}' }
      $request | Add-Member -Name body -Value ([pscustomobject]@{ mode='raw'; raw=$raw; options=[pscustomobject]@{ raw=[pscustomobject]@{ language='json' } } }) -MemberType NoteProperty
    }

    $newItems += [pscustomobject]@{
      name = "https://ornasphere.com/$($r.uri)"
      request = $request
      response = @()
    }
  }
}

# add collection-level auth variables guidance
$fixed = [ordered]@{
  info = [ordered]@{
    _postman_id = $collection.info._postman_id
    name = 'ornasphere-fixed'
    schema = $collection.info.schema
    description = 'Auto-generated from current Laravel API routes. Domain unchanged (https://ornasphere.com). Uses {{token}} for protected routes.'
  }
  variable = @(
    [pscustomobject]@{ key='token'; value=''; type='string' },
    [pscustomobject]@{ key='email'; value=''; type='string' },
    [pscustomobject]@{ key='password'; value=''; type='string' },
    [pscustomobject]@{ key='userId'; value=''; type='string' },
    [pscustomobject]@{ key='otp'; value=''; type='string' }
  )
  item = $newItems
}

$fixed | ConvertTo-Json -Depth 40 | Set-Content $outPath
(Get-Content $outPath -Raw | ConvertFrom-Json) | Out-Null
Write-Output "Generated: $outPath"
Write-Output "Total requests: $($newItems.Count)"
