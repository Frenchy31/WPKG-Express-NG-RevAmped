Set-StrictMode -Version 3.0

$script:SuccessExitCodes = @(0, 3010)
$script:MaximumLogBytes = 1048576

<#
.SYNOPSIS
Normalizes a server URL, or throws when it cannot be used safely.

.DESCRIPTION
wpkg.js passes config.xml values through eval(), so the URL is restricted to characters that cannot
leave a JScript string. Plain HTTP needs an explicit switch: the agent runs feed commands as SYSTEM
on whatever network a laptop joins, so a spoofed server would execute code on it.
#>
function Assert-ServerUrl {
    param(
        [Parameter(Mandatory)][string]$ServerUrl,
        [switch]$AllowInsecureHttp
    )

    $trimmedUrl = $ServerUrl.TrimEnd('/')
    if ($trimmedUrl -notmatch '^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~-]+)*$') {
        throw "Invalid server URL '$ServerUrl'. Credentials are not supported."
    }
    if ($trimmedUrl -match '^http://' -and -not $AllowInsecureHttp) {
        throw "Refusing plain HTTP for '$ServerUrl': use HTTPS, or -AllowInsecureHttp on a test network."
    }

    return $trimmedUrl
}

<#
.SYNOPSIS
Tells whether a response body is a hosts feed. A captive portal also answers 200, with HTML.
#>
function Test-HostsFeedContent {
    param([AllowEmptyString()][AllowNull()][string]$Content)

    return [bool]($Content -match '^\s*(<\?xml[^>]*\?>\s*)?<(\w+:)?wpkg[\s/>]')
}

<#
.SYNOPSIS
Decides what a trigger should do. Returns Run, or the reason to skip.
#>
function Get-AgentDecision {
    param(
        [Parameter(Mandatory)][bool]$IsWpkgRunning,
        [Nullable[datetime]]$LastSuccess,
        [Parameter(Mandatory)][datetime]$Now,
        [Parameter(Mandatory)][int]$MinIntervalMinutes,
        [Parameter(Mandatory)][scriptblock]$TestServer
    )

    if ($IsWpkgRunning) {
        return 'SkipAlreadyRunning'
    }
    if ($null -ne $LastSuccess -and $LastSuccess -le $Now -and ($Now - $LastSuccess).TotalMinutes -lt $MinIntervalMinutes) {
        return 'SkipRecentRun'
    }
    if (-not (& $TestServer)) {
        return 'SkipServerUnreachable'
    }

    return 'Run'
}

<#
.SYNOPSIS
Returns the wpkg.js switches of an unattended run. No reboot: users are working during the day.
#>
function Get-WpkgArguments {
    param([Parameter(Mandatory)][string]$WpkgScript)

    return @('//NoLogo', $WpkgScript, '/synchronize', '/quiet', '/nonotify', '/noreboot')
}

<#
.SYNOPSIS
Builds the config.xml of the agent. Backslashes are doubled because wpkg.js evaluates the values.
#>
function New-AgentConfigXml {
    param(
        [Parameter(Mandatory)][string]$ServerUrl,
        [Parameter(Mandatory)][string]$LogDirectory
    )

    $escapedLogDirectory = $LogDirectory.Replace('\', '\\')

    return @"
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <param name='hosts_path' value='$ServerUrl/hosts.xml' />
    <param name='profiles_path' value='$ServerUrl/profiles.xml' />
    <param name='packages_path' value='$ServerUrl/packages.xml' />
    <param name='quitonerror' value='true' />
    <param name='log_file_path' value='$escapedLogDirectory' />
</config>
"@
}

<#
.SYNOPSIS
Builds the scheduled task: SYSTEM, hidden, run on boot, on network connection and periodically.
#>
function New-AgentTaskXml {
    param(
        [Parameter(Mandatory)][string]$RunScript,
        [Parameter(Mandatory)][int]$RepeatMinutes
    )

    $arguments = [System.Security.SecurityElement]::Escape(
        "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$RunScript`""
    )
    $networkQuery = [System.Security.SecurityElement]::Escape(
        '<QueryList><Query Id="0" Path="Microsoft-Windows-NetworkProfile/Operational">' +
        '<Select Path="Microsoft-Windows-NetworkProfile/Operational">*[System[EventID=10000]]</Select>' +
        '</Query></QueryList>'
    )

    return @"
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.4" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo>
    <Description>Synchronizes WPKG in the background when the server is reachable.</Description>
  </RegistrationInfo>
  <Triggers>
    <BootTrigger>
      <Delay>PT2M</Delay>
      <Enabled>true</Enabled>
    </BootTrigger>
    <EventTrigger>
      <Delay>PT30S</Delay>
      <Subscription>$networkQuery</Subscription>
      <Enabled>true</Enabled>
    </EventTrigger>
    <TimeTrigger>
      <Repetition>
        <Interval>PT${RepeatMinutes}M</Interval>
        <StopAtDurationEnd>false</StopAtDurationEnd>
      </Repetition>
      <StartBoundary>2026-01-01T00:00:00</StartBoundary>
      <Enabled>true</Enabled>
    </TimeTrigger>
  </Triggers>
  <Principals>
    <Principal id="Author">
      <UserId>S-1-5-18</UserId>
      <RunLevel>HighestAvailable</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <StartWhenAvailable>true</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <AllowStartOnDemand>true</AllowStartOnDemand>
    <Enabled>true</Enabled>
    <Hidden>true</Hidden>
    <ExecutionTimeLimit>PT2H</ExecutionTimeLimit>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>powershell.exe</Command>
      <Arguments>$arguments</Arguments>
    </Exec>
  </Actions>
</Task>
"@
}

<#
.SYNOPSIS
Tells whether a folder ACL lets only SYSTEM and Administrators write, as install.ps1 creates it.

.DESCRIPTION
A standard user can create the agent folder before the first install and own it, or leave it with
the ACL inherited from ProgramData, which lets users add files. Such a folder is never trusted.
#>
function Test-AgentRootAcl {
    param(
        [Parameter(Mandatory)][string]$OwnerSid,
        [Parameter(Mandatory)][bool]$AccessRulesProtected,
        [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$AccessRuleSids
    )

    $trustedSids = @('S-1-5-18', 'S-1-5-32-544')
    if ($trustedSids -notcontains $OwnerSid -or -not $AccessRulesProtected) {
        return $false
    }

    return -not ($AccessRuleSids | Where-Object { $trustedSids -notcontains $_ })
}

<#
.SYNOPSIS
Tells whether the agent folder on disk is a real folder that only SYSTEM and Administrators can write.
#>
function Test-AgentRoot {
    param([Parameter(Mandatory)][string]$Path)

    # A junction sends every write to its target, and its owner can repoint it at any time.
    if ((Get-Item -LiteralPath $Path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) {
        return $false
    }

    $sidType = [Security.Principal.SecurityIdentifier]
    $acl = Get-Acl -LiteralPath $Path
    $ruleSids = @($acl.GetAccessRules($true, $true, $sidType) | ForEach-Object { $_.IdentityReference.Value })

    return Test-AgentRootAcl -OwnerSid $acl.GetOwner($sidType).Value `
        -AccessRulesProtected $acl.AreAccessRulesProtected -AccessRuleSids $ruleSids
}

<#
.SYNOPSIS
Tells whether the WPKG server answers with a real hosts feed.
#>
function Test-WpkgServer {
    param(
        [Parameter(Mandatory)][string]$ServerUrl,
        [int]$TimeoutSeconds = 5
    )

    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    try {
        $response = Invoke-WebRequest -Uri "$ServerUrl/hosts.xml" -UseBasicParsing -TimeoutSec $TimeoutSeconds `
            -MaximumRedirection 0 -ErrorAction Stop
    } catch {
        return $false
    }

    return Test-HostsFeedContent -Content ([string]$response.Content)
}

<#
.SYNOPSIS
Tells whether wpkg.js is already running, whoever started it: nightly task, user or agent.
#>
function Test-WpkgRunning {
    $processes = Get-CimInstance -ClassName Win32_Process -Filter "Name = 'cscript.exe'"

    return [bool]($processes | Where-Object { $_.CommandLine -match 'wpkg[^\\/]*\.js' })
}

<#
.SYNOPSIS
Appends a line to the agent log, keeping one previous file.
#>
function Write-AgentLog {
    param(
        [Parameter(Mandatory)][string]$LogDirectory,
        [Parameter(Mandatory)][string]$Message
    )

    $logFile = Join-Path $LogDirectory 'agent.log'
    if ((Test-Path $logFile) -and (Get-Item $logFile).Length -gt $script:MaximumLogBytes) {
        Move-Item -Path $logFile -Destination "$logFile.1" -Force
    }
    Add-Content -Path $logFile -Value ('{0:yyyy-MM-ddTHH:mm:ss} {1}' -f (Get-Date), $Message)
}

<#
.SYNOPSIS
Runs one trigger: decides, synchronizes, records the result. Returns the process exit code.
#>
function Invoke-WpkgAgentRun {
    param([Parameter(Mandatory)][string]$AgentRoot)

    $settings = Get-Content (Join-Path $AgentRoot 'agent.json') -Raw | ConvertFrom-Json
    $logDirectory = Join-Path $AgentRoot 'logs'
    $stateFile = Join-Path $AgentRoot 'last-success.txt'

    $lastSuccess = $null
    if (Test-Path $stateFile) {
        $lastSuccess = [datetime]::Parse((Get-Content $stateFile -Raw).Trim())
    }

    $serverUrl = $settings.serverUrl
    $decision = Get-AgentDecision -IsWpkgRunning (Test-WpkgRunning) -LastSuccess $lastSuccess -Now (Get-Date) `
        -MinIntervalMinutes $settings.minIntervalMinutes -TestServer { Test-WpkgServer -ServerUrl $serverUrl }
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    Write-AgentLog -LogDirectory $logDirectory -Message "[$identity] decision=$decision"
    if ($decision -ne 'Run') {
        return 0
    }

    $wpkgArguments = Get-WpkgArguments -WpkgScript (Join-Path $AgentRoot 'wpkg.js')
    & cscript.exe @wpkgArguments | Out-File -FilePath (Join-Path $logDirectory 'wpkg-output.log') -Encoding utf8
    $exitCode = $LASTEXITCODE
    Write-AgentLog -LogDirectory $logDirectory -Message "[$identity] wpkg.js exit code $exitCode"

    if ($script:SuccessExitCodes -contains $exitCode) {
        Set-Content -Path $stateFile -Value (Get-Date).ToString('o')
        return 0
    }

    return 1
}

Export-ModuleMember -Function Assert-ServerUrl, Test-HostsFeedContent, Get-AgentDecision, Get-WpkgArguments,
    New-AgentConfigXml, New-AgentTaskXml, Test-AgentRootAcl, Test-AgentRoot, Test-WpkgServer, Test-WpkgRunning,
    Write-AgentLog, Invoke-WpkgAgentRun
