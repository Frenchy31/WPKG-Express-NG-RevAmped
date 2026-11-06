#!/usr/bin/env bash
# Captures reference XML feeds from the frozen PHP 5.6 stack into the PHPUnit fixtures.
# Usage (from legacy/): sg docker -c ./capture-golden.sh
set -euo pipefail
base=http://127.0.0.1:8081
golden=../web/tests/Fixture/reference/golden
config=/var/www/html/config/wpkgExpress.php

set_option() { # name value
    docker compose exec -T legacy-web sed -i -E "s/'$1' => [0-9]+/'$1' => $2/" "$config"
}

capture() { # scenario
    mkdir -p "$golden/$1"
    for feed in hosts profiles packages; do
        curl -sS -m 20 -o "$golden/$1/$feed.xml" "$base/$feed.xml"
    done
}

set_option formatxml 1; set_option exportdisabled 0; capture default
curl -sS -m 20 -o "$golden"/default/host-view-1.xml "$base/hosts/view/1.xml"
curl -sS -m 20 -o "$golden"/default/host-view-3-disabled.xml "$base/hosts/view/3.xml"
curl -sS -m 20 -o "$golden"/default/profile-view-1.xml "$base/profiles/view/1.xml"
curl -sS -m 20 -o "$golden"/default/package-view-2.xml "$base/packages/view/2.xml"
curl -sS -m 20 -o "$golden"/default/package-view-7-disabled.xml "$base/packages/view/7.xml"

set_option formatxml 0; capture noformat
set_option formatxml 1; set_option exportdisabled 1; capture exportdisabled
set_option exportdisabled 0
