#!/usr/bin/env bash
# Installs WPKG Express NG from a fresh clone: .env, admin password, images, containers, database tables.
#
#   git clone https://github.com/Frenchy31/WPKG-Express-NG-RevAmped.git && cd WPKG-Express-NG-RevAmped && ./install.sh
#
# The admin password is asked for, or read from WPKG_ADMIN_PASSWORD. Without a terminal and without that
# variable, a random password is generated and printed once. Running the script again keeps the existing
# password unless WPKG_ADMIN_PASSWORD is set, and only rebuilds and restarts the stack.
set -euo pipefail

cd "$(dirname "$0")"

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nError: %s\n' "$*" >&2; exit 1; }

step "Checking prerequisites"
command -v docker >/dev/null || fail "Docker is not installed: https://docs.docker.com/engine/install/"
docker compose version >/dev/null 2>&1 || fail "The Docker Compose v2 plugin (docker compose) is not installed."
docker info >/dev/null 2>&1 || fail "Cannot reach the Docker daemon. Start it, or add your user to the docker group
       (sudo usermod -aG docker \"\$USER\"), log in again, or run: sg docker -c ./install.sh"

[ -f .env ] || { cp .env.example .env; chmod 600 .env; }
project=$(docker compose config | sed -n 's/^name: //p')

# Fresh install only: the stack of this project may already hold these resources.
if [ -z "$(docker compose ps -q)" ]; then
    for container in mysql samba; do
        if owner=$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' "$container" 2>/dev/null) \
            && [ "$owner" != "$project" ]; then
            fail "A container named '$container' already exists outside this project. Remove or rename it first."
        fi
    done
    # shellcheck disable=SC2046  # one argument per network ID
    clash=$(docker network inspect $(docker network ls -q) --format '{{ .Name }} {{ range .IPAM.Config }}{{ .Subnet }} {{ end }}' \
        | awk -v own="${project}_backend" '$1 != own && / 172\.19\./ { print $1 }')
    [ -z "$clash" ] || fail "Docker network '$clash' already uses 172.19.0.0/16, which this project needs."
    if command -v ss >/dev/null; then
        for port in 80 139 445; do
            [ -z "$(ss -Hltn "sport = :$port")" ] || fail "Port $port is already in use on this host."
        done
    fi
fi

step "Building the images"
docker compose build wpkg

step "Setting the admin password"
if [ -n "${WPKG_ADMIN_PASSWORD:-}" ] || ! grep -q "^WPKG_ADMIN_PASSWORD_HASH='[^']" .env; then
    generated=false
    if [ -z "${WPKG_ADMIN_PASSWORD:-}" ] && [ -t 0 ]; then
        read -rsp "Admin password: " WPKG_ADMIN_PASSWORD; echo
        read -rsp "Repeat it: " confirmation; echo
        [ -n "$WPKG_ADMIN_PASSWORD" ] || fail "The password is empty."
        [ "$WPKG_ADMIN_PASSWORD" = "$confirmation" ] || fail "The two passwords differ."
    elif [ -z "${WPKG_ADMIN_PASSWORD:-}" ]; then
        WPKG_ADMIN_PASSWORD=$(head -c 15 /dev/urandom | base64 | tr -d '/+=')
        generated=true
    fi
    # The password goes through the environment, not the command line, so that ps does not show it.
    hash=$(WPKG_INSTALL_PASSWORD="$WPKG_ADMIN_PASSWORD" docker run --rm -e WPKG_INSTALL_PASSWORD \
        -e SECURITY_SALT=hash-only my-wpkg sh -c 'bin/cake hash_password "$WPKG_INSTALL_PASSWORD"' | tail -n 1) \
        || fail "Could not hash the password."
    # shellcheck disable=SC2016  # literal prefix of an Argon2id hash
    case "$hash" in
        '$argon2id$'*) ;;
        *) fail "Could not hash the password: $hash" ;;
    esac
    { grep -v '^WPKG_ADMIN_PASSWORD_HASH=' .env || true; printf "WPKG_ADMIN_PASSWORD_HASH='%s'\n" "$hash"; } > .env.new
    chmod 600 .env.new
    mv .env.new .env
    [ "$generated" = false ] || printf 'Generated admin password, shown only once: %s\n' "$WPKG_ADMIN_PASSWORD"
else
    echo "Kept the password already set in .env."
fi

step "Starting mysql, samba and wpkg"
docker compose up -d --wait mysql samba wpkg

step "Creating the database tables"
docker compose exec -T -u www-data wpkg bin/cake migrations migrate

cat <<EOF

WPKG Express NG is installed.

  Web interface   http://localhost/   (user: admin)
  Feeds for wpkg.js   http://<this host>/hosts.xml, profiles.xml, packages.xml
  Samba share     \\\\<this host>\\WPKG   (test account: administrateur / wpkgtest)

The values in compose.yaml (SECURITY_SALT, MySQL passwords, Samba account) are for local use only:
change them before any shared deployment. Every name the workstations use in hosts_path must be listed
in WPKG_ALLOWED_HOSTS. The Windows test VM is optional: see wpkg-test/README.md.
EOF
