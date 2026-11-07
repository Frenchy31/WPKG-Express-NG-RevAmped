# Entry point of the scheduled task.
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'WpkgAgent.psm1') -Force
exit (Invoke-WpkgAgentRun -AgentRoot $PSScriptRoot)
