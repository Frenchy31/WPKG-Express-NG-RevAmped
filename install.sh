#!/usr/bin/env bash
# Installs WPKG Express NG from a fresh clone: .env, component env files, admin password, images, containers,
# database tables.
#
#   git clone https://github.com/Frenchy31/WPKG-Express-NG-RevAmped.git && cd WPKG-Express-NG-RevAmped && ./install.sh
#
# The components to install are the services/<component>/compose.yaml files listed in COMPOSE_FILE (.env).
# Each component with a <component>.env.example gets a <component>.env copy, which holds its values.
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
if ! grep -q '^COMPOSE_FILE=' .env; then
    printf '\n%s\n' "$(grep '^COMPOSE_FILE=' .env.example)" >> .env
fi

compose_file=$(sed -n 's/^COMPOSE_FILE=//p' .env | tail -n 1)
components=$(printf '%s\n' "$compose_file" | tr ':' '\n' | sed -nE 's#^(\./)?services/([^/]+)/compose\.yaml$#\2#p' | tr '\n' ' ')
enabled() { case " $components " in *" $1 "*) return 0 ;; *) return 1 ;; esac; }
enabled wpkg || fail "COMPOSE_FILE in .env must list services/wpkg/compose.yaml."

http_port=${WPKG_HTTP_PORT:-$(sed -n 's/^WPKG_HTTP_PORT=//p' .env | tail -n 1)}
http_port=${http_port:-80}

for component in $components; do
    env_file="services/$component/$component.env"
    if [ -f "$env_file.example" ] && [ ! -f "$env_file" ]; then
        cp "$env_file.example" "$env_file"
        chmod 600 "$env_file"
    fi
done

# Installations made before the components kept the password hashes in .env.
wpkg_env=services/wpkg/wpkg.env
for hash_name in WPKG_ADMIN_PASSWORD_HASH WPKG_XML_PASSWORD_HASH; do
    if grep -q "^$hash_name='[^']" .env && ! grep -q "^$hash_name='[^']" "$wpkg_env"; then
        { grep -v "^$hash_name=" "$wpkg_env" || true; grep "^$hash_name=" .env | tail -n 1; } > "$wpkg_env.new"
        chmod 600 "$wpkg_env.new"
        mv "$wpkg_env.new" "$wpkg_env"
    fi
    if grep -q "^$hash_name=" .env; then
        { grep -v "^$hash_name=" .env || true; } > .env.new
        chmod 600 .env.new
        mv .env.new .env
    fi
done

project=$(docker compose config | sed -n 's/^name: //p')

# Fresh install only: the stack of this project may already hold these resources.
if [ -z "$(docker compose ps -q)" ]; then
    containers=""
    ports="$http_port"
    enabled mysql && containers="$containers mysql"
    if enabled samba; then
        containers="$containers samba"
        ports="$ports 139 445"
    fi
    if enabled windows-vm; then
        containers="$containers wpkg-test-client"
        ports="$ports 8006 3389"
    fi
    for container in $containers; do
        if owner=$(docker inspect --type container --format '{{ index .Config.Labels "com.docker.compose.project" }}' "$container" 2>/dev/null) \
            && [ "$owner" != "$project" ]; then
            fail "A container named '$container' already exists outside this project. Remove or rename it first."
        fi
    done
    # shellcheck disable=SC2046  # one argument per network ID
    clash=$(docker network inspect $(docker network ls -q) --format '{{ .Name }} {{ range .IPAM.Config }}{{ .Subnet }} {{ end }}' \
        | awk -v own="${project}_backend" '$1 != own && / 172\.19\./ { print $1 }')
    [ -z "$clash" ] || fail "Docker network '$clash' already uses 172.19.0.0/16, which this project needs."
    if command -v ss >/dev/null; then
        for port in $ports; do
            [ -z "$(ss -Hltn "sport = :$port")" ] || fail "Port $port is already in use on this host."
        done
    fi
fi

step "Building the images"
docker compose build wpkg

step "Setting the admin password"
if [ -n "${WPKG_ADMIN_PASSWORD:-}" ] || ! grep -q "^WPKG_ADMIN_PASSWORD_HASH='[^']" "$wpkg_env"; then
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
    { grep -v '^WPKG_ADMIN_PASSWORD_HASH=' "$wpkg_env" || true; printf "WPKG_ADMIN_PASSWORD_HASH='%s'\n" "$hash"; } > "$wpkg_env.new"
    chmod 600 "$wpkg_env.new"
    mv "$wpkg_env.new" "$wpkg_env"
    [ "$generated" = false ] || printf 'Generated admin password, shown only once: %s\n' "$WPKG_ADMIN_PASSWORD"
else
    echo "Kept the password already set in $wpkg_env."
fi

up_services=(wpkg)
enabled mysql && up_services+=(mysql)
enabled samba && up_services+=(samba)
step "Starting ${up_services[*]}"
docker compose up -d --wait "${up_services[@]}"

step "Creating the database tables"
docker compose exec -T -u www-data wpkg bin/cake migrations migrate

# Data of the previous layout stays where it is: the user moves it.
old_data=""
if [ -d wpkg-test/storage ] && [ ! -e services/windows-vm/storage ]; then
    old_data="$old_data
  mv wpkg-test/storage services/windows-vm/storage"
fi
if [ -d samba-conf/shared/wpkg/logs ] && [ -n "$(find samba-conf/shared/wpkg/logs -mindepth 1 ! -name .gitkeep -print -quit)" ]; then
    old_data="$old_data
  mv samba-conf/shared/wpkg/logs/* services/samba/share/wpkg/logs/"
fi
if [ -d samba-conf/shared/wpkg/agent ] && [ ! -e services/samba/share/wpkg/agent ]; then
    old_data="$old_data
  mv samba-conf/shared/wpkg/agent services/samba/share/wpkg/agent"
fi
if [ -n "$old_data" ]; then
    printf '\nData of the previous layout was found. Move it by hand (nothing was moved):%s\n' "$old_data"
fi

url="http://localhost"
[ "$http_port" = 80 ] || url="$url:$http_port"

cat <<EOF

WPKG Express NG is installed.

  Web interface   $url/   (user: admin)
  Feeds for wpkg.js   http://<this host>:$http_port/hosts.xml, profiles.xml, packages.xml
EOF
if enabled samba; then
    printf '  Samba share     \\\\<this host>\\WPKG   (test account: administrateur / wpkgtest)\n'
fi
cat <<EOF

The values in services/*/*.env (SECURITY_SALT, MySQL passwords, Samba account) are for local use only:
change them before any shared deployment. Every name the workstations use in hosts_path must be listed
in WPKG_ALLOWED_HOSTS (services/wpkg/wpkg.env).

The Windows test VM is optional: add services/windows-vm/compose.yaml to COMPOSE_FILE in .env, run
./install.sh again, then start it with: docker compose up -d wpkg-test-client
See services/windows-vm/README.md.
EOF
