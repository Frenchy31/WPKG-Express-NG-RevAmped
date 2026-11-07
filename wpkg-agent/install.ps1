<#
.SYNOPSIS
Installs the WPKG background agent. Run elevated, or from a WPKG package.

.PARAMETER ServerUrl
Base URL of WPKG Express, for example https://wpkg.example.org. It must be reachable from this machine.

.PARAMETER MinIntervalMinutes
Minimum time between two successful synchronizations.

.PARAMETER RepeatMinutes
How often the task checks for the server, besides boot and network connection.

.PARAMETER WpkgScript
wpkg.js to copy. Defaults to the one next to the agent directory on the share.

.PARAMETER AllowInsecureHttp
Accept a plain HTTP server. Test networks only: see README.md.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ServerUrl,
    [ValidateRange(0, 1440)][int]$MinIntervalMinutes = 60,
    [ValidateRange(1, 1440)][int]$RepeatMinutes = 30,
    [string]$WpkgScript,
    [switch]$AllowInsecureHttp
)

$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'WpkgAgent.psm1') -Force

# Windows PowerShell leaves $PSScriptRoot empty in param() defaults.
if (-not $WpkgScript) {
    $WpkgScript = Join-Path (Split-Path $PSScriptRoot -Parent) 'wpkg.js'
}

$principal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'install.ps1 must run elevated.'
}

$normalizedUrl = Assert-ServerUrl -ServerUrl $ServerUrl -AllowInsecureHttp:$AllowInsecureHttp

# Directly under ProgramData: a parent folder created by a standard user would let them swap this one.
$agentRoot = Join-Path $env:ProgramData 'WpkgAgent'
$administrators = New-Object Security.Principal.SecurityIdentifier 'S-1-5-32-544'
$system = New-Object Security.Principal.SecurityIdentifier 'S-1-5-18'

# SYSTEM executes this folder, so a writable file here would be a privilege escalation.
$security = New-Object Security.AccessControl.DirectorySecurity
$security.SetOwner($administrators)
$security.SetAccessRuleProtection($true, $false)
foreach ($identity in @($system, $administrators)) {
    $security.AddAccessRule((New-Object Security.AccessControl.FileSystemAccessRule(
        $identity, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'
    )))
}

if (-not (Test-Path -LiteralPath $agentRoot)) {
    [void][IO.Directory]::CreateDirectory($agentRoot, $security)
}

# Also covers a folder created between both calls: CreateDirectory then keeps it as it is.
if (-not (Test-AgentRoot -Path $agentRoot)) {
    throw "$agentRoot was not created by install.ps1, so a standard user may control it. " +
        'Find out who created it, delete it, then install again.'
}

$logDirectory = Join-Path $agentRoot 'logs'
[void](New-Item -ItemType Directory -Path $logDirectory -Force)
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'WpkgAgent.psm1') -Destination $agentRoot -Force
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'run.ps1') -Destination $agentRoot -Force
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'uninstall.ps1') -Destination $agentRoot -Force
Copy-Item -LiteralPath $WpkgScript -Destination (Join-Path $agentRoot 'wpkg.js') -Force

Set-Content -Path (Join-Path $agentRoot 'config.xml') -Encoding UTF8 `
    -Value (New-AgentConfigXml -ServerUrl $normalizedUrl -LogDirectory $logDirectory)
Set-Content -Path (Join-Path $agentRoot 'agent.json') -Encoding ASCII -Value (ConvertTo-Json @{
    serverUrl = $normalizedUrl
    minIntervalMinutes = $MinIntervalMinutes
})

$taskXml = New-AgentTaskXml -RunScript (Join-Path $agentRoot 'run.ps1') -RepeatMinutes $RepeatMinutes
[void](Register-ScheduledTask -TaskPath '\WPKG\' -TaskName 'Agent' -Xml $taskXml -Force)

Write-Output "WPKG agent installed in $agentRoot for $normalizedUrl"
