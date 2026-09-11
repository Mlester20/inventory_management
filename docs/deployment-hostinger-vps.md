# Deployment — Hostinger VPS

Reference for deploying SAIMS (Laravel 12 + Nginx + MySQL) from `main` to a Hostinger VPS, and for
redeploying on every future push. Live, interactive version (with copy buttons and a progress
tracker): https://claude.ai/code/artifact/ad4084aa-68bc-4de9-8d8c-bdb8ff89c906

## Gotchas learned the hard way on this VPS

This box turned out to already have **CloudPanel** pre-installed (check hPanel's VPS Overview for a
"Cloudpanel" card). It changes a few things silently, and each one cost real time to track down:

- Nginx's `include` line (`grep -n include /etc/nginx/nginx.conf`) only picks up files ending in
  `.conf` — a site file named plain `inventory` (no extension) gets silently ignored forever. No
  error, nothing in `nginx -t`. Name it `inventory.conf`.
- PHP-FPM per version listens on its own **TCP port**, not a Unix socket — check
  `grep listen /etc/php/8.2/fpm/pool.d/*.conf` before assuming
  `unix:/run/php/php8.2-fpm.sock` will work. On this VPS it was `127.0.0.1:17000`.
- A leftover `/etc/nginx/sites-enabled/default.conf` (from an earlier Certbot run, predating this
  deployment) had `server_name saims.tech; return 444;` blocks — these silently swallowed every
  request before it ever reached the real vhost, producing `ERR_EMPTY_RESPONSE` in the browser with
  no useful error anywhere. `grep -rl "yourdomain" /etc/nginx/` to find stray matches before
  debugging anything else.
- Certbot's `--nginx` auto-installer kept re-patching that same stray `default.conf` (re-adding the
  `return 444` block, just with a cert attached) instead of the real vhost — even after deleting the
  stray block, even after choosing "reinstall existing certificate." Safer path:
  `certbot certonly --nginx -d yourdomain.com` (obtains the cert only, touches no Nginx config), then
  add the `listen 443 ssl;` block yourself.
- Don't run the stock `php artisan db:seed` against production — `DatabaseSeeder` also creates 10
  random Faker users plus fake demo suppliers/customers/products, meant for local development. Create
  only the real account(s) actually needed via `php artisan tinker`.
- If `mysql -u root` / `sudo mysql` returns `Access denied ... (using password: NO)`, MySQL already
  has a real root password set (not the usual passwordless `auth_socket` a fresh install would have).
  See the reset recipe below rather than guessing passwords.
- When testing HTTPS from the VPS itself before DNS has propagated, `curl -H "Host: ..."` is **not**
  enough — that only sets the HTTP header, not the TLS SNI, and you'll get a confusing
  `unrecognized name` TLS alert. Use `curl https://yourdomain.com --resolve yourdomain.com:443:127.0.0.1`
  instead, which sets the SNI correctly.

## Checkpoints

### 1. Connect & update

```bash
ssh root@YOUR_VPS_IP
apt update && apt upgrade -y
```

### 2. Install the LEMP stack + PHP 8.2

Skip anything already installed if the VPS came with CloudPanel or a similar panel.

```bash
apt install -y nginx mysql-server
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-tokenizer
```

### 3. Install Composer & Node

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

### 4. Create the database

```sql
-- mysql -u root  (or: sudo mysql)
CREATE DATABASE inventory_app;
CREATE USER 'inventory_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON inventory_app.* TO 'inventory_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

If that fails with `Access denied`, reset root first, then retry the block above:

```bash
sudo systemctl stop mysql
sudo mkdir -p /var/run/mysqld && sudo chown mysql:mysql /var/run/mysqld
sudo mysqld_safe --skip-grant-tables --skip-networking &
mysql -u root
```
```sql
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'NewPass!';
FLUSH PRIVILEGES;
EXIT;
```
```bash
sudo pkill mysqld
sudo systemctl start mysql
```

### 5. Clone the repo

```bash
cd /var/www
git clone https://github.com/Mlester20/inventory_management.git
cd inventory_management
git checkout main
```

### 6. Install dependencies & build assets

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 7. Configure `.env`

```bash
cp .env.example .env
nano .env
```
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_DATABASE=inventory_app
DB_USERNAME=inventory_user
DB_PASSWORD=STRONG_PASSWORD_HERE
```
```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
```

### 8. Set permissions

```bash
chown -R www-data:www-data /var/www/inventory_management
chmod -R 755 /var/www/inventory_management
chmod -R 775 storage bootstrap/cache
```

### 9. Configure Nginx

Document root points at `/public`, not the repo root. Confirm the include pattern first:

```bash
grep -n "include" /etc/nginx/nginx.conf
# look for: include /etc/nginx/sites-enabled/*.conf;
# if it ends in *.conf, the site file below MUST be named *.conf too
```

`/etc/nginx/sites-available/inventory.conf`:
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/inventory_management/public;

    index index.php;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        # if that socket doesn't exist, check:
        #   grep listen /etc/php/8.2/fpm/pool.d/*.conf
        # and use that address instead, e.g. fastcgi_pass 127.0.0.1:17000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
```bash
ln -s /etc/nginx/sites-available/inventory.conf /etc/nginx/sites-enabled/inventory.conf
nginx -t
systemctl restart nginx
# verify it's actually loaded (should print your server_name block):
sudo nginx -T 2>&1 | grep -A5 "server_name yourdomain.com"
```

### 10. Enable SSL

```bash
apt install -y certbot python3-certbot-nginx
certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com
```

Add this block to `inventory.conf` yourself (certonly does not touch Nginx config):
```nginx
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/inventory_management/public;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    index index.php;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
```bash
nginx -t
systemctl restart nginx
# test without waiting on DNS propagation:
curl -v https://yourdomain.com --resolve yourdomain.com:443:127.0.0.1
```

### 11. Cache for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the one real admin account here too — not the stock seeder:
```bash
php artisan tinker --execute="\App\Models\User::create(['name' => 'Your Name', 'email' => 'you@example.com', 'password' => bcrypt('ChangeMe!'), 'role' => 'admin', 'is_active' => true]);"
```

## Redeploying on every future push

Nginx / SSL / PHP-FPM config only needs touching again if the domain, PHP version, or file paths
change — none of that is part of a normal code update.

```bash
cd /var/www/inventory_management
git pull origin main
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

For a riskier update, wrap it with `php artisan down` before and `php artisan up` after, so visitors
see a "be right back" page instead of a half-applied deploy.
