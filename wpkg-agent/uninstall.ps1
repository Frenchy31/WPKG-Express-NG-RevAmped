<#
.SYNOPSIS
Removes the WPKG background agent. Packages installed by WPKG stay in place.
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'WpkgAgent.psm1') -Force

Unregister-ScheduledTask -TaskPath '\WPKG\' -TaskName 'Agent' -Confirm:$false -ErrorAction SilentlyContinue
$agentRoot = Join-Path $env:ProgramData 'WpkgAgent'
if (-not (Test-Path -LiteralPath $agentRoot)) {
    return
}

# A recursive delete as SYSTEM would follow junctions planted in a folder a standard user controls.
if (-not (Test-AgentRoot -Path $agentRoot)) {
    throw "$agentRoot was not created by install.ps1: not deleting it. Find out who created it."
}
Remove-Item -LiteralPath $agentRoot -Recurse -Force
