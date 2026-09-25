# Quick production bring-up helper (Windows PowerShell)
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")

if (-not (Test-Path ".env.production")) {
  Copy-Item ".env.production.example" ".env.production"
  Write-Host "Created .env.production from example. Fill APP_KEY, DB_PASSWORD, domain, and mail before going live."
}

if (-not (Test-Path ".env")) {
  @"
DOMAIN=localhost
DB_DATABASE=gitlife
DB_USERNAME=gitlife
DB_PASSWORD=gitlife-change-me
"@ | Set-Content ".env"
  Write-Host "Created root .env with localhost defaults."
}

docker compose -f docker-compose.prod.yml up -d --build
Write-Host "Stack starting. Run .\deploy\smoke.ps1 when containers are healthy. See docs/DEPLOY.md."
