#!/bin/sh
# Development container only (Dockerfile.dev). Never used by Dockerfile.prod.
#
# The project is bind-mounted at /app and the JWT private key in it is mode 0600, owned by
# the host user. PHP-FPM's default worker (www-data) cannot read it, so minting a token
# fails with a 500. Run the workers as the owner of the mount instead: the key keeps its
# owner-only mode and nothing becomes world- or group-readable.
#
# Under rootless Docker the host user appears as uid 0 inside the container (and is an
# unprivileged host user outside), so a uid-0 owner is honoured too, with php-fpm -R.
# Production runs as www-data and mounts the key as a secret (see DOCKER.md).
set -eu

uid=$(stat -c %u /app)
gid=$(stat -c %g /app)

printf '[www]\nuser = %s\ngroup = %s\n' "$uid" "$gid" > /usr/local/etc/php-fpm.d/zz-dev-user.conf

if [ "$uid" = "0" ]; then
    exec php-fpm -F -R
fi
exec php-fpm -F
