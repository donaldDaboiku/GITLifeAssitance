# Production / local stack smoke checks (PowerShell)
# Usage:
#   .\deploy\smoke.ps1
#   .\deploy\smoke.ps1 -BaseUrl https://app.example.com
#   .\deploy\smoke.ps1 -BaseUrl http://127.0.0.1:8000 -ApiOnly

param(
  [string]$BaseUrl = "",
  [switch]$ApiOnly
)

$ErrorActionPreference = "Continue"
Set-Location (Join-Path $PSScriptRoot "..")

if (-not $BaseUrl) {
  if (Test-Path ".env") {
    $domainLine = Get-Content ".env" | Where-Object { $_ -match '^\s*DOMAIN=' } | Select-Object -First 1
    if ($domainLine) {
      $domain = ($domainLine -split '=', 2)[1].Trim()
      if ($domain -and $domain -ne "localhost") {
        $BaseUrl = "https://$domain"
      }
    }
  }
  if (-not $BaseUrl) {
    $BaseUrl = "http://127.0.0.1:8000"
    $ApiOnly = $true
    Write-Host "No DOMAIN set - smoking local API at $BaseUrl (ApiOnly)."
  }
}

$BaseUrl = $BaseUrl.TrimEnd('/')
$failed = 0

Write-Host "Smoking $BaseUrl ..."

# 1) Health
try {
  $res = Invoke-WebRequest -Uri "$BaseUrl/up" -UseBasicParsing -TimeoutSec 20
  if ($res.StatusCode -ne 200) { throw "status $($res.StatusCode)" }
  Write-Host "OK  API health /up"
} catch {
  Write-Host "FAIL API health /up - $($_.Exception.Message)"
  $failed++
}

# 2) Unauthenticated API must be 401 (not 500)
try {
  $code = 0
  try {
    Invoke-WebRequest -Uri "$BaseUrl/api/user" -Headers @{ Accept = "application/json" } -UseBasicParsing -TimeoutSec 20 | Out-Null
    $code = 200
  } catch {
    if ($_.Exception.Response) {
      $code = [int]$_.Exception.Response.StatusCode
    } else {
      throw $_
    }
  }
  if ($code -ne 401) { throw "expected 401, got $code" }
  Write-Host "OK  Unauthenticated /api/user is 401"
} catch {
  Write-Host "FAIL Unauthenticated /api/user - $($_.Exception.Message)"
  $failed++
}

if (-not $ApiOnly) {
  try {
    $res = Invoke-WebRequest -Uri "$BaseUrl/" -UseBasicParsing -TimeoutSec 20
    if ($res.StatusCode -ne 200) { throw "status $($res.StatusCode)" }
    Write-Host "OK  SPA /"
  } catch {
    Write-Host "FAIL SPA / - $($_.Exception.Message)"
    $failed++
  }

  try {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $res = Invoke-WebRequest -Uri "$BaseUrl/sanctum/csrf-cookie" -WebSession $session -UseBasicParsing -TimeoutSec 20
    if ($res.StatusCode -notin 200, 204) { throw "status $($res.StatusCode)" }
    Write-Host "OK  Sanctum CSRF cookie"
  } catch {
    Write-Host "FAIL Sanctum CSRF cookie - $($_.Exception.Message)"
    $failed++
  }
}

if ($failed -gt 0) {
  Write-Host ""
  Write-Host "$failed check(s) failed."
  exit 1
}

Write-Host ""
Write-Host "Smoke passed against $BaseUrl"
Write-Host "Still do manually: register/login, create payment, mark paid, scheduler logs, mail, web push."
