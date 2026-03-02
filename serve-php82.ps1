param(
  [string]$BindHost = '0.0.0.0',
  [int]$Port = 8000
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectRoot

.\php82-artisan.ps1 serve --host=$BindHost --port=$Port
