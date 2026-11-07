# Installs Pester when missing, then runs the agent tests. Used locally and by the CI.
$ErrorActionPreference = 'Stop'

if (-not (Get-Module -ListAvailable Pester | Where-Object Version -GE '5.0.0')) {
    Set-PSRepository PSGallery -InstallationPolicy Trusted
    Install-Module Pester -MinimumVersion 5.0.0 -Scope CurrentUser -Force
}

$configuration = New-PesterConfiguration
$configuration.Run.Path = $PSScriptRoot
$configuration.Run.Exit = $true
Invoke-Pester -Configuration $configuration
