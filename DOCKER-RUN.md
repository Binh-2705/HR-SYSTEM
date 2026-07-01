# Run project with Docker

## 1) First-time setup

From project root:

```powershell
cd c:\xampp\htdocs\du_an2
Copy-Item laravel_app\.env.docker.example laravel_app\.env
```

## 2) Build and start services

```powershell
docker compose up -d --build
```

Services:
- Web: http://localhost:8080
- MySQL: localhost:3307 (inside docker network: host `db`, port `3306`)

## 3) Prepare Laravel app

```powershell
docker compose exec web composer install
# generate key once
docker compose exec web php laravel_app/artisan key:generate
# optional if you want to rerun migrations
docker compose exec web php laravel_app/artisan migrate --seed
# clear caches
docker compose exec web php laravel_app/artisan optimize:clear
```

## 4) Open application

Go to: http://localhost:8080

Do not use `/du_an2` path when running this Docker setup.

## 5) Useful commands

```powershell
# stop
docker compose down

# stop and remove db volume (reset database)
docker compose down -v

# view logs
docker compose logs -f web
docker compose logs -f db
```

## 6) If you still see Not Found

```powershell
# verify Apache vhost path
 docker compose exec web bash -lc "apache2ctl -S"

# ensure rewrite module is enabled
 docker compose exec web bash -lc "apache2ctl -M | grep rewrite"

# ensure Laravel public index exists
 docker compose exec web bash -lc "ls -la /var/www/html/laravel_app/public/index.php"
```
