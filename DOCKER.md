# Docker

This project ships `Dockerfile.dev` and `Dockerfile.prod` plus the `docker/` nginx and supervisor
configs they copy in. It does not ship a compose file: build this directory as one service in your own
project-level `docker-compose.yaml`.

Both images are `php:8.3-fpm-bookworm` with nginx and supervisor (Debian, not Alpine). The container
listens on port 8000; `GET /health` is the health check.

| | `Dockerfile.dev` | `Dockerfile.prod` |
|---|---|---|
| Build | single stage, dev tools (git, vim, ...), `composer install` with dev packages | multi-stage, runtime packages only |
| PHP | errors displayed, 512M | OPcache (`validate_timestamps=0`), errors hidden |
| Runs as | PHP-FPM workers as the owner of the bind-mounted project (`docker/dev-fpm-user.sh`) | `www-data` |

## Development

`composer dev` builds `Dockerfile.dev`, mounts the project at `/app` and serves
http://localhost:8000 (`composer stop`, `composer logs`, `composer shell`, `composer clean`).

Without Docker, use the PHP built-in server (never in production):

```bash
php -S 127.0.0.1:9100 -t public public/index.php
```

## Production

```bash
docker build -f Dockerfile.prod -t my-api:latest .
```

A minimal service in your compose file (the API talks to the StoneScriptDB Gateway, never to
PostgreSQL directly). `.env` is not in the image (see `.dockerignore`), so everything the app needs
must be in `environment:` or secrets, including `AUTH_MODE`: without it the framework default
`builtin` applies, and every token from your auth service is refused with 401 while `/health` still
answers 200.

```yaml
services:
  api:
    image: my-api:latest
    environment:
      APP_ENV: production
      DEBUG_MODE: "false"
      DB_GATEWAY_URL: http://gateway:9000
      DB_GATEWAY_PLATFORM: your-platform-code
      DB_GATEWAY_SCHEMA_NAME: main
      JWT_ISSUER: https://api.yourdomain.com
      JWT_PRIVATE_KEY_PATH: /run/secrets/jwt-private.pem
      JWT_PUBLIC_KEY_PATH: /run/secrets/jwt-public.pem
      AUTH_MODE: external                     # external | hybrid | builtin; same value as in .env.example
      AUTH_SERVICE_URL: http://auth:3139      # external/hybrid
      AUTH_ISSUER: https://auth.yourdomain.com
      PLATFORM_CODE: your-platform-code
      TRUSTED_PROXIES: 172.18.0.2             # your reverse proxy, explicit address or CIDR
    secrets:
      - source: jwt_private_key
        target: jwt-private.pem
        uid: "33"
        gid: "33"
        mode: 0400
      - source: jwt_public_key
        target: jwt-public.pem
        uid: "33"
        gid: "33"
        mode: 0444

secrets:
  jwt_private_key:
    file: ./keys/jwt-private.pem
  jwt_public_key:
    file: ./keys/jwt-public.pem
```

`uid`, `gid` and `mode` on a secret are applied by Swarm; plain `docker compose` bind-mounts the host
file with the host's owner and mode (see below). `docker compose config` validates the file.

`.env.example` documents every variable; allowed origins live in `src/config/allowed-origins.php`.
Run `php stone env:check` as the PHP-FPM user before you deploy.

### The JWT signing key: mount it, never bake it

`keys/`, `*.pem`, `*.key`, `.env` and `.env.*` (except `.env.example`) are in `.dockerignore`, so
neither the signing key nor your environment is in the image. The production workers run as
`www-data` (uid 33), so the mounted private key must be readable by uid 33 and by no one else:

- **Docker Swarm / Kubernetes secrets:** set the owner and mode on the secret
  (Swarm long syntax `uid: "33"`, `gid: "33"`, `mode: 0400`; Kubernetes `fsGroup`/`defaultMode`).
- **Plain `docker compose`** (file-based secrets are bind mounts that keep the host file's owner and
  mode, so a `0600` key owned by your user is unreadable inside the container): on the host,
  `sudo chown 33:33 keys/jwt-private.pem && chmod 0400 keys/jwt-private.pem`, or keep your owner and
  give group 33 read access with `chgrp 33 keys/jwt-private.pem && chmod 0440 keys/jwt-private.pem`.
  Never `0644`.

Development is different on purpose: the key stays `0600` on your machine and the dev container
runs PHP-FPM as the owner of the bind-mounted project (`docker/dev-fpm-user.sh`), so nothing is made
group- or world-readable.

### Reverse proxy

The container's nginx is an inner nginx: it passes `X-Forwarded-For` through unchanged, and PHP trusts
it only from the addresses in `TRUSTED_PROXIES`. Without `TRUSTED_PROXIES` every visitor behind a
proxy shares one rate-limit bucket and the site answers HTTP 429. Your outer proxy must overwrite
`X-Forwarded-For` with the address it saw (the framework's `docs/CLIENT-IP-AND-NGINX-STANDARD.md`, 3b).

## Production checklist

- `Dockerfile.prod`, `APP_ENV=production`, `DEBUG_MODE=false`
- `JWT_ISSUER` set to your API URL; signing key mounted as a secret
- real origins in `src/config/allowed-origins.php`, then `php stone auth:check-origins`
- `TRUSTED_PROXIES` set to your reverse proxy
- HTTPS terminated at the load balancer or outer nginx
- `php stone env:check` passes as the PHP-FPM user

## Logs and health

```bash
docker logs -f <container>                          # application and nginx
docker exec <container> tail -f /var/log/php_errors.log
curl http://localhost:8000/health
```

## Troubleshooting

- **Container exits or answers 500 `env_configuration_invalid`:** a required value in `.env` is empty
  or `.env` is unreadable. Run `php stone env:check` inside the container.
- **Token minting returns 500 in a container:** the private key is not readable by the PHP-FPM user (see above).
- **Port in use:** `sudo lsof -i :8000`.
- **Rebuild without cache:** `docker build --no-cache -f Dockerfile.prod -t my-api .`

## CI

```yaml
- name: Build image
  run: docker build -f Dockerfile.prod -t my-api:${{ github.sha }} .
```
