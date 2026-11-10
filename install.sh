#!/usr/bin/env bash
# Installs WPKG Express NG from a fresh clone, or updates an existing installation: .env, component env
# files, passwords, images, containers, database tables.
#
#   git clone https://github.com/Frenchy31/WPKG-Express-NG-RevAmped.git && cd WPKG-Express-NG-RevAmped && ./install.sh
#
# The components to install are the services/<component>/compose.yaml files listed in COMPOSE_FILE (.env).
# Each component with a <component>.env.example gets a <component>.env copy, which holds its values.
#
# With a terminal the script asks each question, offering the value already saved as the default. Without
# a terminal, each question takes its environment variable, else its default:
#
#   WPKG_SERVER_NAME        server name, gives APP_FULL_BASE_URL (default: hostname -f)
#   WPKG_ALLOWED_HOSTS      names and addresses used by the workstations, comma separated
#                           (default: server name, short name, first address; wpkg and 172.19.0.5 are always added)
#   WPKG_HTTP_PORT          host port of the web interface and the feeds (default: 80)
#   WPKG_TIMEZONE           PHP time zone, APP_DEFAULT_TIMEZONE (default: the host's; UTC on an existing installation)
#   WPKG_LOCALE             interface language, en_US or fr_FR (default: en_US)
#   WPKG_ENABLE_SAMBA       yes or no (default: yes)
#   WPKG_ENABLE_WINDOWS_VM  yes or no (default: no; needs samba, /dev/kvm, free ports 8006 and 3389, disk space)
#   WPKG_ADMIN_PASSWORD     password of the "admin" user (any characters)
#   WPKG_SAMBA_PASSWORD     password of the Samba account "administrateur" (letters and digits only)
#   WPKG_VM_PASSWORD        password of the Windows VM (letters and digits only)
#   WPKG_XML_PROTECT        true or false: protect the XML feeds with a password (default: false)
#   WPKG_XML_PASSWORD       password of the feeds, user "wpkg" (any characters)
#
# SECURITY_SALT and the MySQL passwords are generated on the first install and never shown nor changed.
# A password left empty (or without a terminal and without its variable) is generated and printed once.
# A password already saved is kept unless its variable is set. No password is ever put on a command line.
# Running the script again keeps every saved value, then rebuilds and restarts the stack.
set -euo pipefail

export LC_ALL=C
cd "$(dirname "$0")"

# Development values of installations made before the passwords were generated.
legacy_mysql_root=your_root_password
legacy_mysql_user=your_password
legacy_samba=wpkgtest
legacy_vm=changeme-test
legacy_salt=dev-only-salt-0123456789abcdef0123456789abcdef

wpkg_env=services/wpkg/wpkg.env
mysql_env=services/mysql/mysql.env
tools_env=services/tools/tools.env
samba_env=services/samba/samba.env
vm_env=services/windows-vm/windows-vm.env
vm_credentials=services/windows-vm/oem/smb-credentials.bat

# Set through printf -v by ask and collect_password.
server_name="" allowed_hosts="" http_port="" timezone="" locale="" xml_protect="" want_samba="" want_vm=""
admin_password="" xml_password="" samba_password="" vm_password=""

interactive=false
[ -t 0 ] && interactive=true

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nError: %s\n' "$*" >&2; exit 1; }

# env_get FILE KEY: the last value of KEY in FILE, empty when the file or the key is missing.
env_get() {
    local line value=""
    if [ -f "$1" ]; then
        while IFS= read -r line || [ -n "$line" ]; do
            case $line in "$2="*) value=${line#*=} ;; esac
        done < "$1"
    fi
    printf '%s' "$value"
}

# env_set FILE KEY VALUE: replaces the line of KEY, or appends it. Only bash builtins see VALUE, so that
# no password reaches a command line; the file is written with mode 600 and moved into place.
env_set() {
    local file=$1 key=$2 value=$3 line found=false
    : > "$file.new"
    chmod 600 "$file.new"
    if [ -f "$file" ]; then
        while IFS= read -r line || [ -n "$line" ]; do
            case $line in
                "$key="*)
                    if [ "$found" = false ]; then printf '%s=%s\n' "$key" "$value" >> "$file.new"; fi
                    found=true
                    ;;
                *) printf '%s\n' "$line" >> "$file.new" ;;
            esac
        done < "$file"
    fi
    if [ "$found" = false ]; then printf '%s=%s\n' "$key" "$value" >> "$file.new"; fi
    mv "$file.new" "$file"
}

# env_merge FILE EXAMPLE: appends the keys of EXAMPLE that FILE lacks (new settings of a newer version).
env_merge() {
    local line key
    while IFS= read -r line || [ -n "$line" ]; do
        case $line in
            [A-Z]*=*)
                key=${line%%=*}
                if ! grep -q "^$key=" "$1"; then printf '%s\n' "$line" >> "$1"; fi
                ;;
        esac
    done < "$2"
}

random_alnum() {
    local length=$1 password=""
    while [ "${#password}" -lt "$length" ]; do
        password+=$(head -c 64 /dev/urandom | tr -dc 'A-Za-z0-9')
    done
    printf '%s' "${password:0:length}"
}

# to_bool VALUE: prints yes or no, fails on anything else.
to_bool() {
    case $1 in
        yes | y | Y | YES | Yes | true | 1) printf yes ;;
        no | n | N | NO | No | false | 0) printf no ;;
        *) return 1 ;;
    esac
}

reason=""

# ask RESULT ENV_NAME PROMPT DEFAULT [VALIDATOR]: the value of ENV_NAME, else the answer typed at a
# terminal, else DEFAULT. A VALIDATOR sets $reason and fails on a bad value: the terminal asks again.
ask() {
    local result=$1 env_name=$2 prompt=$3 default=$4 validator=${5:-} value
    if [ -n "${!env_name:-}" ]; then
        value=${!env_name}
        if [ -n "$validator" ] && ! "$validator" "$value"; then fail "$env_name: $reason"; fi
    elif [ "$interactive" = true ]; then
        while :; do
            read -rp "$prompt [$default]: " value
            value=${value:-$default}
            if [ -n "$validator" ] && ! "$validator" "$value"; then
                printf '%s\n' "$reason" >&2
                continue
            fi
            break
        done
    else
        value=$default
        if [ -n "$validator" ] && ! "$validator" "$value"; then fail "default of $env_name: $reason"; fi
    fi
    printf -v "$result" '%s' "$value"
}

# check_password VALUE: only the characters that pass unescaped through DATABASE_URL, USER_1 and a .bat file.
check_password() {
    case $1 in
        *[!A-Za-z0-9]*)
            reason="only the characters A-Z, a-z and 0-9 are allowed: ';' splits the Samba account, '%', '&' and quotes break the batch file."
            return 1
            ;;
    esac
}

generated_passwords=""
password_generated=false

# collect_password RESULT ENV_NAME LABEL CHARSET EMPTY: the value of ENV_NAME, else a password typed twice,
# else a generated one (CHARSET strict: letters and digits only; EMPTY generate: an empty answer generates).
collect_password() {
    local result=$1 env_name=$2 label=$3 charset=$4 empty=$5 first second
    password_generated=false
    if [ -n "${!env_name:-}" ]; then
        first=${!env_name}
        if [ "$charset" = strict ] && ! check_password "$first"; then fail "$env_name: $reason"; fi
    elif [ "$interactive" = true ]; then
        while :; do
            read -rsp "$label: " first; echo
            if [ -z "$first" ]; then
                if [ "$empty" = generate ]; then
                    first=$(random_alnum 24)
                    password_generated=true
                    break
                fi
                echo "The password is empty." >&2
                continue
            fi
            if [ "$charset" = strict ] && ! check_password "$first"; then
                printf '%s\n' "$reason" >&2
                continue
            fi
            read -rsp "Repeat it: " second; echo
            [ "$first" = "$second" ] && break
            echo "The two passwords differ." >&2
        done
    else
        first=$(random_alnum 24)
        password_generated=true
    fi
    printf -v "$result" '%s' "$first"
}

note_generated() {
    generated_passwords="$generated_passwords
  $1: $2"
}

# hash_password VAR: Argon2id hash of the password held in VAR. The password goes through the
# environment of docker run (the name only is on the command line), and PHP reads it there: bin/cake
# hash_password would take it as an argument, which ps shows on the host while the container runs.
# password_hash with PASSWORD_ARGON2ID is what that command calls.
hash_password() {
    local hash
    hash=$(WPKG_INSTALL_PASSWORD="${!1}" docker run --rm -e WPKG_INSTALL_PASSWORD my-wpkg php -r \
        'echo password_hash(getenv("WPKG_INSTALL_PASSWORD"), PASSWORD_ARGON2ID);') \
        || fail "Could not hash the password."
    # shellcheck disable=SC2016  # literal prefix of an Argon2id hash
    case "$hash" in
        '$argon2id$'*) ;;
        *) fail "Could not hash the password: $hash" ;;
    esac
    printf '%s' "$hash"
}

# hash_saved NAME: a hash is saved in wpkg.env, or still in .env (installations made before the components).
hash_saved() {
    [ -n "$(env_get "$wpkg_env" "$1")" ] && [ "$(env_get "$wpkg_env" "$1")" != "''" ] && return 0
    [ -n "$(env_get .env "$1")" ] && [ "$(env_get .env "$1")" != "''" ]
}

image_ready=false
ensure_image() {
    if [ "$image_ready" = false ]; then
        step "Building the wpkg image"
        docker build -f services/wpkg/Dockerfile --target prod -t my-wpkg . >&2
        image_ready=true
    fi
}

valid_timezone() {
    case $1 in
        *[!A-Za-z0-9_/+-]* | '') reason="'$1' is not a time zone name such as Europe/Paris or UTC."; return 1 ;;
    esac
    ensure_image
    if ! WPKG_CHECK_TIMEZONE=$1 docker run --rm -e WPKG_CHECK_TIMEZONE my-wpkg php -r \
        'exit(in_array(getenv("WPKG_CHECK_TIMEZONE"), DateTimeZone::listIdentifiers(), true) ? 0 : 1);'; then
        reason="PHP does not know the time zone '$1' (examples: Europe/Paris, America/New_York, UTC)."
        return 1
    fi
}

valid_locale() {
    case $1 in
        en_US | fr_FR) ;;
        *) reason="'$1' is not a shipped language: use en_US or fr_FR."; return 1 ;;
    esac
}

valid_port() {
    case $1 in
        '' | *[!0-9]*) reason="'$1' is not a port number."; return 1 ;;
    esac
    if [ "$1" -lt 1 ] || [ "$1" -gt 65535 ]; then reason="'$1' is not between 1 and 65535."; return 1; fi
}

valid_server_name() {
    case $1 in
        '' | *[!A-Za-z0-9.-]* | .* | -* | *. | *-) reason="'$1' is not a host name or address (letters, digits, '.' and '-'; no port, no scheme)."; return 1 ;;
    esac
}

valid_hosts() {
    local entry
    local -a entries
    IFS=',' read -ra entries <<< "$1"
    for entry in "${entries[@]}"; do
        entry=${entry// /}
        case $entry in
            *[!A-Za-z0-9._-]*) reason="'$entry' is not a host name or address (letters, digits, '.', '_' and '-'; no port)."; return 1 ;;
        esac
    done
}

valid_yes_no() {
    if ! to_bool "$1" > /dev/null; then reason="'$1' is not yes or no."; return 1; fi
}

valid_true_false() {
    case $1 in
        true | false) ;;
        *) reason="'$1' is not true or false."; return 1 ;;
    esac
}

# dedupe_hosts LIST: LIST without spaces nor duplicates, then wpkg and 172.19.0.5.
dedupe_hosts() {
    local seen="," result="" entry
    local -a entries
    IFS=',' read -ra entries <<< "$1,wpkg,172.19.0.5"
    for entry in "${entries[@]}"; do
        entry=${entry// /}
        [ -n "$entry" ] || continue
        case $seen in *",$entry,"*) continue ;; esac
        seen="$seen$entry,"
        result="$result${result:+,}$entry"
    done
    printf '%s' "$result"
}

host_timezone() {
    local zone=""
    if command -v timedatectl > /dev/null; then zone=$(timedatectl show -p Timezone --value 2> /dev/null || true); fi
    if [ -z "$zone" ] && [ -r /etc/timezone ]; then read -r zone < /etc/timezone || true; fi
    if [ -z "$zone" ] && [ -L /etc/localtime ]; then
        zone=$(readlink /etc/localtime)
        zone=${zone#*zoneinfo/}
    fi
    printf '%s' "${zone:-UTC}"
}

step "Checking prerequisites"
command -v docker > /dev/null || fail "Docker is not installed: https://docs.docker.com/engine/install/"
docker compose version > /dev/null 2>&1 || fail "The Docker Compose v2 plugin (docker compose) is not installed."
docker info > /dev/null 2>&1 || fail "Cannot reach the Docker daemon. Start it, or add your user to the docker group
       (sudo usermod -aG docker \"\$USER\"), log in again, or run: sg docker -c ./install.sh"

# Nothing is written before every answer is collected and checked.
compose_source=.env
[ -f .env ] || compose_source=.env.example
compose_file=$(env_get "$compose_source" COMPOSE_FILE)
[ -n "$compose_file" ] || compose_file=$(env_get .env.example COMPOSE_FILE)
components=$(printf '%s\n' "$compose_file" | tr ':' '\n' | sed -nE 's#^(\./)?services/([^/]+)/compose\.yaml$#\2#p' | tr '\n' ' ')
enabled() { case " $components " in *" $1 "*) return 0 ;; *) return 1 ;; esac; }
enabled wpkg || fail "COMPOSE_FILE in .env must list services/wpkg/compose.yaml."

project=$(docker compose -f compose.yaml config | sed -n 's/^name: //p')
project_label="label=com.docker.compose.project=$project"

existing=false
if docker volume inspect "${project}_mysql_data" > /dev/null 2>&1; then existing=true; fi
vm_storage_found=false
if [ -d wpkg-test/storage ] || [ -d services/windows-vm/storage ]; then vm_storage_found=true; fi

step "Choosing the components"
saved_samba=no
enabled samba && saved_samba=yes
saved_vm=no
enabled windows-vm && saved_vm=yes
ask want_samba WPKG_ENABLE_SAMBA "Enable the Samba share (reports of the workstations)? yes/no" "$saved_samba" valid_yes_no
want_samba=$(to_bool "$want_samba")
valid_vm_choice() {
    valid_yes_no "$1" || return 1
    if [ "$(to_bool "$1")" = yes ] && [ "$want_samba" = no ]; then
        reason="the Windows VM needs the Samba share: enable samba too."
        return 1
    fi
}
ask want_vm WPKG_ENABLE_WINDOWS_VM "Enable the Windows test VM? yes/no" "$saved_vm" valid_vm_choice
want_vm=$(to_bool "$want_vm")

if [ "$want_vm" = yes ]; then
    [ -r /dev/kvm ] && [ -w /dev/kvm ] || fail "The Windows VM needs /dev/kvm, readable and writable by this user (missing, or no access: check the kvm group)."
    own_vm_exists=$(docker ps -aq --filter "$project_label" --filter name=wpkg-test-client)
    own_vm_running=$(docker ps -q --filter "$project_label" --filter name=wpkg-test-client)
    if command -v ss > /dev/null && [ -z "$own_vm_running" ]; then
        for port in 8006 3389; do
            [ -z "$(ss -Hltn "sport = :$port")" ] || fail "Port $port is already in use on this host: the Windows VM needs it."
        done
    fi
    if [ -z "$own_vm_exists" ]; then
        disk_size=$(env_get "$vm_env" DISK_SIZE)
        [ -n "$disk_size" ] || disk_size=$(env_get services/windows-vm/windows-vm.env.example DISK_SIZE)
        disk_size=${disk_size:-48G}
        case $disk_size in
            *[0-9]T) unit_kb=1073741824 ;;
            *[0-9]G) unit_kb=1048576 ;;
            *[0-9]M) unit_kb=1024 ;;
            *) fail "DISK_SIZE '$disk_size' in $vm_env: use a number followed by M, G or T." ;;
        esac
        disk_number=${disk_size%?}
        case $disk_number in '' | *[!0-9]*) fail "DISK_SIZE '$disk_size' in $vm_env: use a number followed by M, G or T." ;; esac
        disk_target=services/windows-vm/storage
        [ -d "$disk_target" ] || disk_target=services/windows-vm
        available_kb=$(df -Pk "$disk_target" | awk 'NR == 2 { print $4 }')
        if [ "$available_kb" -lt $((disk_number * unit_kb)) ]; then
            fail "The Windows VM disk needs $disk_size, but only $((available_kb / 1048576)) GB are free where $disk_target lives. Free some space or lower DISK_SIZE in $vm_env."
        fi
    fi
fi

step "Choosing the server"
saved_url=$(env_get "$wpkg_env" APP_FULL_BASE_URL)
saved_name=${saved_url#http://}
saved_name=${saved_name#https://}
saved_name=${saved_name%%/*}
saved_name=${saved_name%%:*}
if [ -z "$saved_name" ]; then
    saved_name=$(hostname -f 2> /dev/null || hostname)
fi
ask server_name WPKG_SERVER_NAME "Server name used by the workstations" "$saved_name" valid_server_name

saved_hosts=$(env_get "$wpkg_env" WPKG_ALLOWED_HOSTS)
if [ -z "$saved_hosts" ]; then
    first_address=""
    read -r first_address _ <<< "$(hostname -I 2> /dev/null || true)"
    saved_hosts="$server_name,$(hostname -s 2> /dev/null || true),$first_address"
fi
saved_hosts=$(dedupe_hosts "$saved_hosts")
ask allowed_hosts WPKG_ALLOWED_HOSTS "Names and addresses used by the workstations (wpkg and 172.19.0.5 are added)" "$saved_hosts" valid_hosts
allowed_hosts=$(dedupe_hosts "$allowed_hosts")

saved_port=$(env_get .env WPKG_HTTP_PORT)
ask http_port WPKG_HTTP_PORT "HTTP port" "${saved_port:-80}" valid_port

saved_timezone=$(env_get "$wpkg_env" APP_DEFAULT_TIMEZONE)
if [ -z "$saved_timezone" ]; then
    if [ "$existing" = true ]; then saved_timezone=UTC; else saved_timezone=$(host_timezone); fi
fi
ask timezone WPKG_TIMEZONE "Time zone" "$saved_timezone" valid_timezone
previous_timezone=$(env_get "$wpkg_env" APP_DEFAULT_TIMEZONE)
previous_timezone=${previous_timezone:-UTC}
if [ "$existing" = true ] && [ "$timezone" != "$previous_timezone" ]; then
    printf 'Warning: the datetime columns of the database have no time zone. Changing %s to %s shifts the dates already stored.\n' \
        "$previous_timezone" "$timezone"
fi

saved_locale=$(env_get "$wpkg_env" APP_DEFAULT_LOCALE)
ask locale WPKG_LOCALE "Interface language (en_US or fr_FR)" "${saved_locale:-en_US}" valid_locale

step "Choosing the passwords"
legacy_used=""

# Admin password: asked when no hash is saved, or when WPKG_ADMIN_PASSWORD is set.
admin_hash=""
if [ -n "${WPKG_ADMIN_PASSWORD:-}" ] || ! hash_saved WPKG_ADMIN_PASSWORD_HASH; then
    collect_password admin_password WPKG_ADMIN_PASSWORD "Admin password" any reject
    [ "$password_generated" = false ] || note_generated "Admin password (user admin)" "$admin_password"
    ensure_image
    admin_hash=$(hash_password admin_password)
fi

# Feed protection.
saved_protect=$(env_get "$wpkg_env" WPKG_XML_PROTECT)
[ "$saved_protect" = true ] || saved_protect=false
ask xml_protect WPKG_XML_PROTECT "Protect the XML feeds with a password? true/false" "$saved_protect" valid_true_false
xml_hash=""
if [ "$xml_protect" = true ]; then
    if [ -n "${WPKG_XML_PASSWORD:-}" ] || ! hash_saved WPKG_XML_PASSWORD_HASH; then
        collect_password xml_password WPKG_XML_PASSWORD "Feed password (user wpkg, empty: generate one)" any generate
        [ "$password_generated" = false ] || note_generated "Feed password (user wpkg)" "$xml_password"
        ensure_image
        xml_hash=$(hash_password xml_password)
    fi
fi

# SECURITY_SALT.
salt=$(env_get "$wpkg_env" SECURITY_SALT)
salt_replaced=false
if [ -z "$salt" ] || [ "$salt" = "$legacy_salt" ]; then
    [ -z "$salt" ] || salt_replaced=true
    salt=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
fi

# MySQL passwords: generated once, or the development values when the volume was initialised with them.
mysql_root=""
mysql_user=""
mysql_changed=false
if enabled mysql; then
    mysql_root=$(env_get "$mysql_env" MYSQL_ROOT_PASSWORD)
    mysql_user=$(env_get "$mysql_env" MYSQL_PASSWORD)
    if [ -z "$mysql_root" ]; then
        if [ "$existing" = true ]; then mysql_root=$legacy_mysql_root; else mysql_root=$(random_alnum 24); fi
        mysql_changed=true
    fi
    if [ -z "$mysql_user" ]; then
        if [ "$existing" = true ]; then mysql_user=$legacy_mysql_user; else mysql_user=$(random_alnum 24); fi
        mysql_changed=true
    fi
    [ "$mysql_root" != "$legacy_mysql_root" ] && [ "$mysql_user" != "$legacy_mysql_user" ] \
        || legacy_used="$legacy_used
  MySQL passwords (root and wpkg): $legacy_mysql_root / $legacy_mysql_user"
fi

# Samba account.
samba_password=""
if [ "$want_samba" = yes ]; then
    saved_user_1=$(env_get "$samba_env" USER_1)
    samba_password=${saved_user_1#*;}
    [ "$saved_user_1" != "$samba_password" ] || samba_password=""
    if [ -n "${WPKG_SAMBA_PASSWORD:-}" ]; then
        collect_password samba_password WPKG_SAMBA_PASSWORD "Samba password" strict generate
    elif [ -z "$samba_password" ]; then
        if [ "$existing" = true ] && [ "$vm_storage_found" = true ]; then
            samba_password=$legacy_samba
        else
            collect_password samba_password WPKG_SAMBA_PASSWORD "Samba password for the account administrateur (empty: generate one)" strict generate
            [ "$password_generated" = false ] || note_generated "Samba password (account administrateur)" "$samba_password"
        fi
    fi
    [ "$samba_password" != "$legacy_samba" ] || legacy_used="$legacy_used
  Samba password (administrateur): $legacy_samba"
fi

# Windows VM password.
vm_password=""
if [ "$want_vm" = yes ]; then
    vm_password=$(env_get "$vm_env" PASSWORD)
    if [ -n "${WPKG_VM_PASSWORD:-}" ]; then
        collect_password vm_password WPKG_VM_PASSWORD "Windows VM password" strict generate
    elif [ -z "$vm_password" ]; then
        if [ "$existing" = true ] && [ "$vm_storage_found" = true ]; then
            vm_password=$legacy_vm
        else
            collect_password vm_password WPKG_VM_PASSWORD "Windows VM password (empty: generate one)" strict generate
            [ "$password_generated" = false ] || note_generated "Windows VM password (user Administrator)" "$vm_password"
        fi
    fi
    [ "$vm_password" != "$legacy_vm" ] || legacy_used="$legacy_used
  Windows VM password (Administrator): $legacy_vm"
fi

# Fresh install only: the stack of this project may already hold these resources.
if [ -z "$(docker ps -aq --filter "$project_label")" ]; then
    containers=""
    ports="$http_port"
    enabled mysql && containers="$containers mysql"
    if [ "$want_samba" = yes ]; then
        containers="$containers samba"
        ports="$ports 139 445"
    fi
    if [ "$want_vm" = yes ]; then containers="$containers wpkg-test-client"; fi
    for container in $containers; do
        if owner=$(docker inspect --type container --format '{{ index .Config.Labels "com.docker.compose.project" }}' "$container" 2> /dev/null) \
            && [ "$owner" != "$project" ]; then
            fail "A container named '$container' already exists outside this project. Remove or rename it first."
        fi
    done
    # shellcheck disable=SC2046  # one argument per network ID
    clash=$(docker network inspect $(docker network ls -q) --format '{{ .Name }} {{ range .IPAM.Config }}{{ .Subnet }} {{ end }}' \
        | awk -v own="${project}_backend" '$1 != own && / 172\.19\./ { print $1 }')
    [ -z "$clash" ] || fail "Docker network '$clash' already uses 172.19.0.0/16, which this project needs."
    if command -v ss > /dev/null; then
        for port in $ports; do
            [ -z "$(ss -Hltn "sport = :$port")" ] || fail "Port $port is already in use on this host."
        done
    fi
fi

step "Writing the configuration"
[ -f .env ] || { cp .env.example .env; chmod 600 .env; }

# COMPOSE_FILE: same order, same extra entries (compose.local.yaml); only the two optional components change.
new_compose_files=()
last_component=-1
IFS=':' read -ra compose_entries <<< "$(env_get .env COMPOSE_FILE)"
[ "${#compose_entries[@]}" -gt 0 ] && [ -n "${compose_entries[*]}" ] || IFS=':' read -ra compose_entries <<< "$compose_file"
for entry in "${compose_entries[@]}"; do
    case ${entry#./} in
        services/samba/compose.yaml) [ "$want_samba" = yes ] || continue ;;
        services/windows-vm/compose.yaml) [ "$want_vm" = yes ] || continue ;;
    esac
    new_compose_files+=("$entry")
    case ${entry#./} in services/*/compose.yaml) last_component=$((${#new_compose_files[@]} - 1)) ;; esac
done
for pair in "samba:$want_samba" "windows-vm:$want_vm"; do
    component=${pair%%:*}
    if [ "${pair#*:}" = yes ] && ! enabled "$component"; then
        new_compose_files=("${new_compose_files[@]:0:last_component + 1}" "services/$component/compose.yaml" "${new_compose_files[@]:last_component + 1}")
        last_component=$((last_component + 1))
    fi
done
new_compose_value=$(IFS=':'; printf '%s' "${new_compose_files[*]}")
env_set .env COMPOSE_FILE "$new_compose_value"
env_set .env WPKG_HTTP_PORT "$http_port"
compose_file=$new_compose_value
components=$(printf '%s\n' "$compose_file" | tr ':' '\n' | sed -nE 's#^(\./)?services/([^/]+)/compose\.yaml$#\2#p' | tr '\n' ' ')

for component in $components; do
    env_file="services/$component/$component.env"
    if [ -f "$env_file.example" ]; then
        if [ ! -f "$env_file" ]; then
            cp "$env_file.example" "$env_file"
            chmod 600 "$env_file"
        else
            env_merge "$env_file" "$env_file.example"
        fi
    fi
done

# Installations made before the components kept the password hashes in .env.
for hash_name in WPKG_ADMIN_PASSWORD_HASH WPKG_XML_PASSWORD_HASH; do
    if grep -q "^$hash_name='[^']" .env && ! grep -q "^$hash_name='[^']" "$wpkg_env"; then
        env_set "$wpkg_env" "$hash_name" "$(env_get .env "$hash_name")"
    fi
    if grep -q "^$hash_name=" .env; then
        { grep -v "^$hash_name=" .env || true; } > .env.new
        chmod 600 .env.new
        mv .env.new .env
    fi
done

url="http://$server_name"
[ "$http_port" = 80 ] || url="$url:$http_port"
env_set "$wpkg_env" SECURITY_SALT "$salt"
env_set "$wpkg_env" APP_FULL_BASE_URL "$url"
env_set "$wpkg_env" WPKG_ALLOWED_HOSTS "$allowed_hosts"
env_set "$wpkg_env" APP_DEFAULT_TIMEZONE "$timezone"
env_set "$wpkg_env" APP_DEFAULT_LOCALE "$locale"
env_set "$wpkg_env" WPKG_XML_PROTECT "$xml_protect"
[ -z "$admin_hash" ] || env_set "$wpkg_env" WPKG_ADMIN_PASSWORD_HASH "'$admin_hash'"
[ -z "$xml_hash" ] || env_set "$wpkg_env" WPKG_XML_PASSWORD_HASH "'$xml_hash'"

if enabled mysql; then
    env_set "$mysql_env" MYSQL_ROOT_PASSWORD "$mysql_root"
    env_set "$mysql_env" MYSQL_PASSWORD "$mysql_user"
    if [ "$mysql_changed" = true ] || [ -z "$(env_get "$wpkg_env" DATABASE_URL)" ]; then
        env_set "$wpkg_env" DATABASE_URL "mysql://wpkg:$mysql_user@mysql/wpkg?encoding=utf8mb4"
    fi
    if enabled tools && { [ "$mysql_changed" = true ] || [ -z "$(env_get "$tools_env" DATABASE_TEST_URL)" ]; }; then
        env_set "$tools_env" DATABASE_TEST_URL "mysql://wpkg:$mysql_user@mysql/wpkg_test?encoding=utf8mb4"
    fi
fi
if enabled samba; then
    env_set "$samba_env" USER_1 "administrateur;$samba_password"
fi
if enabled windows-vm; then
    env_set "$vm_env" PASSWORD "$vm_password"
    : > "$vm_credentials"
    chmod 600 "$vm_credentials"
    printf 'set SMBUSER=administrateur\r\nset SMBPASS=%s\r\n' "$samba_password" >> "$vm_credentials"
fi

if [ "$salt_replaced" = true ]; then
    echo "The old development SECURITY_SALT was replaced: every open session is logged out."
fi
if [ "$xml_protect" = true ]; then
    echo "Warning: wpkg-agent refuses protected feeds (see wpkg-agent/README.md, \"Flux protégés non gérés\")."
fi

step "Building the images"
docker compose build wpkg

up_services=(wpkg)
enabled mysql && up_services+=(mysql)
enabled samba && up_services+=(samba)
step "Starting ${up_services[*]}"
# --remove-orphans removes the container of a disabled component; its volume and storage folder stay.
docker compose up -d --wait --remove-orphans "${up_services[@]}"

step "Creating the database tables"
docker compose exec -T -u www-data wpkg bin/cake migrations migrate

if enabled windows-vm; then
    step "Starting the Windows VM (Windows installs itself in the background, 15 to 40 minutes)"
    docker compose up -d wpkg-test-client
fi

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

cat <<EOF

WPKG Express NG is installed.

  Web interface   $url/   (user: admin)
  Feeds for wpkg.js   $url/hosts.xml, profiles.xml, packages.xml
EOF
if enabled samba; then
    printf '  Samba share     \\\\%s\\WPKG   (account: administrateur)\n' "$server_name"
fi
if enabled windows-vm; then
    printf '  Windows VM console   http://%s:8006\n' "$server_name"
fi
if [ -n "$generated_passwords" ]; then
    printf '\nGenerated passwords, shown only once, write them down now:%s\n' "$generated_passwords"
fi
if [ -n "$legacy_used" ]; then
    printf '\nDevelopment values of the previous installation are still in use, change them before any shared deployment:%s\n' "$legacy_used"
fi
cat <<EOF

Every name the workstations use in hosts_path must be listed in WPKG_ALLOWED_HOSTS (services/wpkg/wpkg.env).
To change a value, edit its services/*/*.env file and run: docker compose up -d
EOF
