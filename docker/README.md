# Docker local setup

Prerequisites: Docker (Desktop).
Composer and Node/npm should be run inside a container.

Quick start:

1. Review and adjust credentials in `docker-compose.override.yml` before the first start. The committed values are development defaults only.

2. Build and start the local stack:

```bash
docker compose up --build -d
```

3. Install PHP dependencies:

```bash
docker exec -it fantager-web composer install
```

4. Build frontend assets:

```bash
docker exec -it fantager-web npm install
docker exec -it fantager-web npm run build
```

5. Open the app at http://localhost:10001

Database is exposed on host port `10006` and mapped to MariaDB `3306` inside the container.

What this stack does:
- Uses Apache 2.4 on `php:8.5-apache`.
- Mounts the whole repository into `/var/www/html`, so Symfony can access `config/`, `src/`, `vendor/` and `public/`.
- Serves the application from Symfony's standard `public/` document root.

Notes:
	- PHP configuration is tuned for local development defaults used by the project.
	- Frontend assets are built inside the `fantager-web` container so the build environment is reproducible and matches CI. Node and build tools are provided in the PHP image for a single-container development workflow.
	- If you need extra PHP extensions, add them to `docker/Dockerfile` and rebuild the image.
