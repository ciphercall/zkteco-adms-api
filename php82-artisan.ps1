param(
  [Parameter(Mandatory = $false, ValueFromRemainingArguments = $true)]
  [string[]]$Args
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-Php82 {
  $candidates = @()

  if ($env:PHP_82_PATH) {
    $candidates += $env:PHP_82_PATH
  }

  if ($env:LARAGON_PHP) {
    $candidates += $env:LARAGON_PHP
  }

  if ($env:XAMPP_PHP) {
    $candidates += $env:XAMPP_PHP
  }

  $laragonRoots = @(
    'C:\laragon\bin\php',
    'D:\laragon\bin\php'
  )

  foreach ($root in $laragonRoots) {
    if (-not (Test-Path -LiteralPath $root)) { continue }
    try {
      $phpDirs = Get-ChildItem -Path $root -Directory -ErrorAction Stop |
        Sort-Object Name -Descending
      foreach ($dir in $phpDirs) {
        $phpExe = Join-Path $dir.FullName 'php.exe'
        if (Test-Path -LiteralPath $phpExe) {
          $candidates += $phpExe
        }
      }
    } catch {
      # ignore scanning errors
    }
  }

  $candidates += @(
    'F:\\xampp\\php\\php.exe',
    'C:\\xampp\\php\\php.exe',
    'C:\\laragon\\bin\\php\\php.exe',
    'D:\\laragon\\bin\\php\\php.exe'
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

  throw "PHP 8.2+ not found. Set PHP_82_PATH (or LARAGON_PHP/XAMPP_PHP) to your php.exe."
}

$php82 = Resolve-Php82
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$artisan = Join-Path $projectRoot 'artisan'

if (-not (Test-Path -LiteralPath $artisan)) {
  throw "artisan not found at: $artisan"
}

& $php82 $artisan @Args
