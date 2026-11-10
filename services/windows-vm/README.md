# wpkg.js test environment

A real Windows VM (via QEMU/KVM, orchestrated as the `wpkg-test-client` Compose
service) used to exercise `wpkg-client/wpkg.js` end-to-end: real WMI/registry, a
real download, a real silent install, against the actual `samba` share.

Not native Windows *containers* — those require the Docker daemon itself to run
on a Windows host, which this project doesn't. `dockurr/windows` boots a genuine
Windows disk image under KVM and exposes it like any other container.

## One-time setup

Nothing manual: `USER_1` (`administrateur;wpkgtest`) in `services/samba/samba.env`
makes the `samba` container create the "administrateur" Samba user referenced in
`services/samba/smb.conf`'s `valid users` automatically on every start, with a simple
fixed password (`wpkgtest`) for local testing only. `services/windows-vm/oem/install.bat`'s
`SMBPASS` already matches it. Change (or remove) that `USER_1` value before this
is anything other than a throwaway local test environment.

## Bring it up

The VM is a component that is off by default. Add `services/windows-vm/compose.yaml` to `COMPOSE_FILE`
in `.env`, then create its settings (`./install.sh` does it, or copy `windows-vm.env.example` to
`windows-vm.env`). It needs `/dev/kvm`, a 48 GB disk in `services/windows-vm/storage/` and the free
ports 8006 and 3389. Also keep the `samba` component enabled.

```
docker compose up -d wpkg-test-client
```

- Watch the Windows install at `http://localhost:8006` (noVNC). First boot
  (installing Windows itself) takes roughly 15-40 minutes.
- Once installed, `services/windows-vm/oem/install.bat` runs automatically: it maps the
  share as `Z:`, does one immediate `/dryrun /debug` run of `wpkg.js`, and
  registers a scheduled task that runs a real `wpkg.js /synchronize` every 15
  minutes.
- For interactive/manual runs, connect over RDP at `localhost:3389`
  (Administrator / the `PASSWORD` set in `services/windows-vm/windows-vm.env`) and run
  `cscript Z:\wpkg\wpkg.js` yourself from an elevated `cmd.exe`.

## What it's testing

`services/samba/share/wpkg/config.xml` points `hosts_path`, `profiles_path` and `packages_path` at the
`wpkg` service (`http://172.19.0.5/...`). To test against the XML files of the share instead, set
them back to `packages`, `profiles` and `hosts`.

The data set in `e2e/` is uploaded through the `/admin` page of the application, so a run covers
upload, import, feed generation and the real client:

- `e2e-marker` writes a file with a command containing `&` and is checked by a `logical atleast`,
  the two cases the PHP 5 application could not generate correctly;
- `7zip` downloads and silently installs the real 7-Zip.

The hostname of the current VM is `ADMINIS-XXXXXXX`, not the `HOSTNAME` set in `services/windows-vm/windows-vm.env`. The
data set matches it with `adminis-.+`.

## Running a scenario

`install.bat` only runs on the first boot, and its scheduled task may not see `Z:`. For a controlled
run, open an elevated `cmd.exe` on the desktop (noVNC, RDP, or `sendkey` on the QEMU monitor socket
`/run/shm/monitor.sock` inside the container) and run:

```
net use Z: \\172.19.0.4\wpkg wpkgtest /user:administrateur /persistent:yes
cscript //NoLogo Z:\wpkg\wpkg.js /synchronize /noreboot /debug > Z:\wpkg\logs\run.txt 2>&1
```

Add `/dryrun` to change nothing. The output lands in `services/samba/share/wpkg/logs/`.

## Verifying a run

- `run.txt` lists the feeds read, the packages handled and the commands executed.
- Running `wpkg.js` a second time should report nothing to do.
- Removing a package from the profile in the web interface should uninstall it on the next run.

The results of the PHP 8 validation, failure scenarios included, are in `TESTS.md`.

**Warning:** a valid profile that loses its packages uninstalls them. Do not try this outside the VM.

## Testing the nomad agent

`e2e/nomad/` deploys `wpkg-agent` through WPKG itself. Copy the agent to the share first:

```
mkdir -p services/samba/share/wpkg/agent && cp wpkg-agent/*.ps1 wpkg-agent/*.psm1 services/samba/share/wpkg/agent/
```

The virtual cable is unplugged from the host, through the QEMU monitor of the container:

```
docker exec wpkg-test-client sh -c 'printf "set_link net0 off\n" | timeout 2 nc -U /run/shm/monitor.sock'
```

Use `on` to plug it back. Windows then sees `Media disconnected`, then a real network connection.

## Tearing down

```
docker compose stop wpkg-test-client
```

`./services/windows-vm/storage` holds the VM disk and is kept by default so you don't
have to reinstall Windows next time — delete it for a clean slate.
