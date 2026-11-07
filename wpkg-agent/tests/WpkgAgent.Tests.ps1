BeforeAll {
    Import-Module (Join-Path $PSScriptRoot '..' 'WpkgAgent.psm1') -Force
}

Describe 'Assert-ServerUrl' {
    It 'accepts HTTPS and removes the trailing slash' {
        Assert-ServerUrl -ServerUrl 'https://wpkg.example.org/' | Should -Be 'https://wpkg.example.org'
        Assert-ServerUrl -ServerUrl 'https://wpkg.example.org:8443/app' | Should -Be 'https://wpkg.example.org:8443/app'
    }

    It 'refuses plain HTTP by default' {
        { Assert-ServerUrl -ServerUrl 'http://wpkg.example.org' } | Should -Throw '*Refusing plain HTTP*'
    }

    It 'accepts plain HTTP on explicit request' {
        Assert-ServerUrl -ServerUrl 'http://172.19.0.5' -AllowInsecureHttp | Should -Be 'http://172.19.0.5'
    }

    It 'refuses credentials' {
        { Assert-ServerUrl -ServerUrl 'https://user:secret@wpkg.example.org' } | Should -Throw '*Invalid server URL*'
    }

    It 'refuses characters that could leave the string evaluated by wpkg.js: <url>' -ForEach @(
        @{ url = 'https://wpkg.example.org/"; evil(); "' }
        @{ url = "https://wpkg.example.org/'" }
        @{ url = 'https://wpkg.example.org/\x' }
        @{ url = 'https://wpkg.example.org/a b' }
        @{ url = 'ftp://wpkg.example.org' }
    ) {
        { Assert-ServerUrl -ServerUrl $url -AllowInsecureHttp } | Should -Throw '*Invalid server URL*'
    }
}

Describe 'Test-HostsFeedContent' {
    It 'recognizes a hosts feed' {
        Test-HostsFeedContent -Content "<?xml version=`"1.0`" encoding=`"UTF-8`"?>`n<wpkg>`n</wpkg>" | Should -BeTrue
        Test-HostsFeedContent -Content '<wpkg/>' | Should -BeTrue
        Test-HostsFeedContent -Content '<hosts:wpkg xmlns:hosts="http://www.wpkg.org/hosts"/>' | Should -BeTrue
    }

    It 'rejects a captive portal page' {
        Test-HostsFeedContent -Content '<!DOCTYPE html><html><body>Hotel Wi-Fi: wpkg</body></html>' | Should -BeFalse
    }

    It 'rejects an empty answer' {
        Test-HostsFeedContent -Content '' | Should -BeFalse
        Test-HostsFeedContent -Content $null | Should -BeFalse
    }
}

Describe 'Get-AgentDecision' {
    BeforeAll {
        $now = [datetime]'2026-11-07T10:00:00'
        $reachable = { $true }
        $unreachable = { $false }
    }

    It 'runs when the server answers and nothing recent happened' {
        Get-AgentDecision -IsWpkgRunning $false -LastSuccess $now.AddHours(-2) -Now $now -MinIntervalMinutes 60 -TestServer $reachable |
            Should -Be 'Run'
    }

    It 'runs on a first start' {
        Get-AgentDecision -IsWpkgRunning $false -LastSuccess $null -Now $now -MinIntervalMinutes 60 -TestServer $reachable |
            Should -Be 'Run'
    }

    It 'skips away from the server' {
        Get-AgentDecision -IsWpkgRunning $false -LastSuccess $null -Now $now -MinIntervalMinutes 60 -TestServer $unreachable |
            Should -Be 'SkipServerUnreachable'
    }

    It 'skips after a recent success, without contacting the server' {
        $failIfCalled = { throw 'The server must not be contacted' }

        Get-AgentDecision -IsWpkgRunning $false -LastSuccess $now.AddMinutes(-5) -Now $now -MinIntervalMinutes 60 -TestServer $failIfCalled |
            Should -Be 'SkipRecentRun'
    }

    It 'skips while wpkg.js is running' {
        Get-AgentDecision -IsWpkgRunning $true -LastSuccess $null -Now $now -MinIntervalMinutes 60 -TestServer $reachable |
            Should -Be 'SkipAlreadyRunning'
    }

    It 'does not throttle with an interval of 0' {
        Get-AgentDecision -IsWpkgRunning $false -LastSuccess $now -Now $now -MinIntervalMinutes 0 -TestServer $reachable |
            Should -Be 'Run'
    }
}

Describe 'Get-WpkgArguments' {
    It 'runs without any interaction and never reboots' {
        $arguments = Get-WpkgArguments -WpkgScript 'C:\agent\wpkg.js'

        $arguments | Should -Contain '/synchronize'
        $arguments | Should -Contain '/quiet'
        $arguments | Should -Contain '/nonotify'
        $arguments | Should -Contain '/noreboot'
        $arguments[1] | Should -Be 'C:\agent\wpkg.js'
    }
}

Describe 'New-AgentConfigXml' {
    BeforeAll {
        [xml]$config = New-AgentConfigXml -ServerUrl 'https://wpkg.example.org' -LogDirectory 'C:\ProgramData\WpkgAgent\logs'
        $parameter = { param($name) ($config.config.param | Where-Object name -EQ $name).value }
    }

    It 'points the client at the feeds' {
        & $parameter 'hosts_path' | Should -Be 'https://wpkg.example.org/hosts.xml'
        & $parameter 'profiles_path' | Should -Be 'https://wpkg.example.org/profiles.xml'
        & $parameter 'packages_path' | Should -Be 'https://wpkg.example.org/packages.xml'
    }

    It 'stops on a broken feed instead of carrying on' {
        & $parameter 'quitonerror' | Should -Be 'true'
    }

    It 'doubles backslashes, because wpkg.js evaluates the values' {
        & $parameter 'log_file_path' | Should -Be 'C:\\ProgramData\\WpkgAgent\\logs'
    }
}

Describe 'New-AgentTaskXml' {
    BeforeAll {
        [xml]$task = New-AgentTaskXml -RunScript 'C:\ProgramData\WpkgAgent\run.ps1' -RepeatMinutes 30
    }

    It 'runs as SYSTEM' {
        $task.Task.Principals.Principal.UserId | Should -Be 'S-1-5-18'
        $task.Task.Principals.Principal.RunLevel | Should -Be 'HighestAvailable'
    }

    It 'reacts to a network connection' {
        $task.Task.Triggers.EventTrigger.Subscription | Should -Match 'Microsoft-Windows-NetworkProfile/Operational'
        $task.Task.Triggers.EventTrigger.Subscription | Should -Match 'EventID=10000'
    }

    It 'also runs at boot and periodically' {
        $task.Task.Triggers.BootTrigger | Should -Not -BeNullOrEmpty
        $task.Task.Triggers.TimeTrigger.Repetition.Interval | Should -Be 'PT30M'
    }

    It 'works on battery, which is how laptops usually run' {
        $task.Task.Settings.DisallowStartIfOnBatteries | Should -Be 'false'
        $task.Task.Settings.StopIfGoingOnBatteries | Should -Be 'false'
    }

    It 'never overlaps and stays hidden' {
        $task.Task.Settings.MultipleInstancesPolicy | Should -Be 'IgnoreNew'
        $task.Task.Settings.Hidden | Should -Be 'true'
    }

    It 'quotes the script path' {
        $task.Task.Actions.Exec.Arguments | Should -Match '-File "C:\\ProgramData\\WpkgAgent\\run\.ps1"'
    }
}

Describe 'Test-AgentRootAcl' {
    It 'trusts the folder created by install.ps1, owned by <owner>' -ForEach @(
        @{ owner = 'S-1-5-32-544' }
        @{ owner = 'S-1-5-18' }
    ) {
        Test-AgentRootAcl -OwnerSid $owner -AccessRulesProtected $true -AccessRuleSids @('S-1-5-18', 'S-1-5-32-544') |
            Should -BeTrue
    }

    It 'refuses a folder owned by a standard user' {
        Test-AgentRootAcl -OwnerSid 'S-1-5-21-1-2-3-1001' -AccessRulesProtected $true `
            -AccessRuleSids @('S-1-5-18', 'S-1-5-32-544') | Should -BeFalse
    }

    It 'refuses a folder inheriting the ProgramData ACL, which lets users add files' {
        Test-AgentRootAcl -OwnerSid 'S-1-5-32-544' -AccessRulesProtected $false `
            -AccessRuleSids @('S-1-5-18', 'S-1-5-32-544') | Should -BeFalse
    }

    It 'refuses a folder granting rights to <sid>' -ForEach @(
        @{ sid = 'S-1-5-32-545' }
        @{ sid = 'S-1-3-0' }
        @{ sid = 'S-1-5-21-1-2-3-1001' }
    ) {
        Test-AgentRootAcl -OwnerSid 'S-1-5-32-544' -AccessRulesProtected $true `
            -AccessRuleSids @('S-1-5-18', 'S-1-5-32-544', $sid) | Should -BeFalse
    }
}
