param(
  [Parameter(Mandatory = $false, ValueFromRemainingArguments = $true)]
  [string[]]$Args
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-Php82 {
  $candidates = @()

  if ($env:XAMPP_PHP) {
    $candidates += $env:XAMPP_PHP
  }

  $candidates += @(
    'F:\\xampp\\php\\php.exe',
    'C:\\xampp\\php\\php.exe'
  )

  try {
    $cmd = Get-Command php -ErrorAction Stop
    if ($cmd -and $cmd.Source) {
      $candidates += $cmd.Source
    }
  } catch {
    # ignore
  }

  foreach ($php in ($candidates | Where-Object { $_ } | Select-Object -Unique)) {
    if (-not (Test-Path -LiteralPath $php)) { continue }

    $verLine = & $php -r "echo PHP_VERSION;" 2>$null
    if (-not $verLine) { continue }

    try {
      $v = [version]$verLine
      if ($v.Major -gt 8 -or ($v.Major -eq 8 -and $v.Minor -ge 2)) {
        return $php
      }
    } catch {
      # ignore parse failures
    }
  }

  throw "PHP 8.2+ not found. Set XAMPP_PHP to your php.exe (example: `$env:XAMPP_PHP='F:\\xampp\\php\\php.exe')."
}

$php82 = Resolve-Php82
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$artisan = Join-Path $projectRoot 'artisan'

if (-not (Test-Path -LiteralPath $artisan)) {
  throw "artisan not found at: $artisan"
}

& $php82 $artisan @Args
