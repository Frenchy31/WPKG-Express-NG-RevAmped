@echo off
:: Auto-run once by dockurr/windows on first boot (from the /oem volume).
:: Turns the VM into a repeatable, unattended WPKG test client: map the
:: Samba share, then register + immediately trigger a scheduled task that
:: runs wpkg.js, so results can be read back from the Linux host over the
:: share without needing to RDP in every time.

set SMBUSER=administrateur
set SMBPASS=wpkgtest
set SHARE=\\172.19.0.4\wpkg

:: SMBPASS above must match compose.yaml's samba.environment.USER_1 value.

net use Z: %SHARE% %SMBPASS% /user:%SMBUSER% /persistent:yes

schtasks /create /tn "WPKG Run" /sc minute /mo 15 /ru SYSTEM /f ^
    /tr "cscript.exe //B //NoLogo Z:\wpkg\wpkg.js /synchronize"

:: Also run once immediately so a first result is available without
:: waiting for the schedule.
cscript.exe //B //NoLogo Z:\wpkg\wpkg.js /synchronize /dryrun /debug
