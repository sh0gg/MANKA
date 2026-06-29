# Guía de despliegue de MANKA en Virtualmin

**Dominio objetivo:** `app.kiwilan.org`  
**Stack servidor:** Linux + PHP 8.4 + PostgreSQL 16 + Apache (Virtualmin) o Nginx  
**App:** Symfony 8.0 / PHP 8.4

---

## 0. Requisitos previos en el servidor

Antes de empezar, el servidor debe tener instalado:

- **PHP 8.4** con las extensiones: `pdo_pgsql`, `pgsql`, `intl`, `zip`, `opcache`, `gd`, `ctype`, `iconv`
- **PostgreSQL 16** (cliente y servidor)
- **Composer 2**
- **Git**
- **Node.js** no es necesario (Tailwind se gestiona con el bundle PHP)

Verificar extensiones PHP:
```bash
php -m | grep -E "pdo_pgsql|intl|zip|opcache|gd|ctype|iconv"
```

Si falta alguna (en Debian/Ubuntu):
```bash
sudo apt install php8.4-pgsql php8.4-intl php8.4-zip php8.4-gd
```

---

## 1. Clonar el repositorio

Conectarse por SSH al servidor. Decidir dónde vive el código. En Virtualmin la estructura habitual es:

```
/home/kiwilan/domains/app.kiwilan.org/
```

El `public_html/` de Virtualmin será el document root, pero **la app de Symfony no va ahí directamente**. Dos opciones:

### Opción A — Código fuera del public_html (recomendada)

```bash
cd /home/kiwilan/domains/app.kiwilan.org/
# Clonar al lado de public_html, no dentro
git clone https://REPO_URL manka
```

Estructura resultante:
```
/home/kiwilan/domains/app.kiwilan.org/
├── manka/           ← código Symfony
│   ├── public/      ← este es el document root real
│   ├── src/
│   └── ...
└── public_html/     ← Virtualmin apunta aquí por defecto (cambiar)
```

### Opción B — Código dentro de public_html

```bash
cd /home/kiwilan/domains/app.kiwilan.org/public_html/
git clone https://REPO_URL .
```

Con esta opción, el document root de Virtualmin debe apuntarse a `public_html/public/`.

---

## 2. Instalar dependencias PHP

```bash
cd /ruta/al/codigo  # /home/kiwilan/domains/app.kiwilan.org/manka o public_html

composer install --no-dev --optimize-autoloader --no-interaction
```

> `--no-dev` excluye las herramientas de desarrollo (PHPUnit, Maker, Profiler).  
> `--optimize-autoloader` genera el classmap optimizado para producción.

---

## 3. Configurar variables de entorno

Crear el fichero `.env.local` (nunca se sube al repositorio, solo existe en el servidor):

```bash
nano .env.local
```

Contenido:
```dotenv
APP_ENV=prod
APP_SECRET=CAMBIAR_POR_UN_STRING_ALEATORIO_DE_32_CHARS

DATABASE_URL="postgresql://USUARIO_BD:PASSWORD_BD@127.0.0.1:5432/NOMBRE_BD?serverVersion=16&charset=utf8"

DEFAULT_URI=https://app.kiwilan.org

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null
```

**Cómo generar APP_SECRET:**
```bash
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
```

**Datos de la base de datos:** los encontramos en Virtualmin > Bases de datos PostgreSQL > la BD creada para este subdominio. Normalmente el usuario y nombre de BD coinciden con el nombre del virtual server.

---

## 4. Limpiar y precalentar la caché de producción

```bash
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
```

---

## 5. Ejecutar las migraciones de base de datos

```bash
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
```

Esto crea todas las tablas: `user`, `device`, `issue`, `issue_category`, `observation`, `api_token`, `messenger_messages`.

---

## 6. Construir los assets

### Tailwind CSS (obligatorio)

```bash
APP_ENV=prod php bin/console tailwind:build --minify
```

Esto descarga el binario de Tailwind (si no está ya) y genera `public/assets/styles/app.css` minificado.

### JavaScript / ImportMap

```bash
APP_ENV=prod php bin/console importmap:install
APP_ENV=prod php bin/console asset-map:compile
```

`asset-map:compile` vuelca todos los assets al directorio `public/assets/` con hashes de versión para cache-busting.

---

## 7. Crear el primer usuario administrador

MANKA no tiene registro público. El primer admin se crea con este comando:

```bash
APP_ENV=prod php bin/console app:create-admin
```

El comando pedirá nombre, apellidos, email y contraseña. Una vez creado, el resto de usuarios se gestionan desde el panel web `/admin/users`.

---

## 8. Permisos de ficheros

El proceso de Apache/PHP-FPM necesita escribir en `var/` (caché, logs) y en `public/assets/`:

```bash
# Sustituir www-data por el usuario de PHP-FPM de tu servidor (puede ser php-fpm, apache, etc.)
sudo chown -R www-data:www-data var/ public/assets/
sudo chmod -R 775 var/ public/assets/
```

Si el código pertenece al usuario `kiwilan` de Virtualmin y PHP corre como ese usuario (PHP-FPM en modo Virtualmin):
```bash
chown -R kiwilan:kiwilan var/ public/assets/
chmod -R 775 var/ public/assets/
```

---

## 9. Configurar el Virtual Host en Virtualmin/Apache

En Virtualmin, el document root se configura en:
**Virtualmin > app.kiwilan.org > Edit Virtual Server > Website document root**

Cambiar de `/home/kiwilan/domains/app.kiwilan.org/public_html` a:
```
/home/kiwilan/domains/app.kiwilan.org/manka/public
```

Si no puedes cambiar el document root desde Virtualmin, edita manualmente la configuración de Apache:

```bash
sudo nano /etc/apache2/sites-available/app.kiwilan.org.conf
# o la ruta equivalente en tu servidor
```

El VirtualHost debe quedar así:

```apache
<VirtualHost *:80>
    ServerName app.kiwilan.org
    DocumentRoot /home/kiwilan/domains/app.kiwilan.org/manka/public

    <Directory /home/kiwilan/domains/app.kiwilan.org/manka/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
        DirectoryIndex index.php
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/kiwilan_error.log
    CustomLog ${APACHE_LOG_DIR}/kiwilan_access.log combined
</VirtualHost>
```

Activar `mod_rewrite` si no está activo:
```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### .htaccess de Symfony

Symfony no incluye `.htaccess` por defecto en Symfony 8. Hay dos formas de resolverlo:

**Opción 1 (recomendada): instalar apache-pack**
```bash
composer require symfony/apache-pack --no-dev
```
Esto añade un `.htaccess` adecuado en `public/`.

**Opción 2: crear el `.htaccess` manualmente**

```bash
nano public/.htaccess
```

Contenido:
```apache
DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{REQUEST_URI}::$0 ^(/.+)/(.*)::\2$
    RewriteRule .* - [E=BASE:%1]

    RewriteCond %{HTTP:Authorization} .+
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%0]

    RewriteCond %{ENV:REDIRECT_STATUS} =""
    RewriteRule ^index\.php(?:/(.*)|$) %{ENV:BASE}/$1 [R=301,L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ %{ENV:BASE}/index.php [L]
</IfModule>

<IfModule !mod_rewrite.c>
    <IfModule mod_alias.c>
        RedirectMatch 307 ^/$ /index.php/
    </IfModule>
</IfModule>
```

---

## 10. HTTPS con Let's Encrypt

Virtualmin gestiona Let's Encrypt de forma nativa:

**Virtualmin > app.kiwilan.org > SSL Certificate > Let's Encrypt**

Solicitar certificado para `app.kiwilan.org`. Una vez emitido, Virtualmin configura automáticamente la redirección HTTP → HTTPS.

Verificar que el `.env.local` tenga:
```dotenv
DEFAULT_URI=https://app.kiwilan.org
```

---

## 11. Acceso privado (restricción por IP o red)

El dominio es público pero el acceso debe ser privado. Opciones:

### Opción A — Restricción por IP en Apache

En el VirtualHost o en el `.htaccess`:
```apache
<Directory /home/kiwilan/domains/app.kiwilan.org/manka/public>
    Require ip 192.168.1.0/24   # red local de KiwiAtlántico
    Require ip 88.12.34.56      # IP pública de Galicloud
</Directory>
```

### Opción B — Autenticación HTTP básica como segunda capa

```bash
htpasswd -c /home/kiwilan/.htpasswd manka_user
```

En el VirtualHost:
```apache
<Directory ...>
    AuthType Basic
    AuthName "MANKA — Acceso restrinxido"
    AuthUserFile /home/kiwilan/.htpasswd
    Require valid-user
</Directory>
```

### Opción C — VPN

El acceso solo desde la VPN de KiwiAtlántico. La configuración es externa a MANKA.

---

## 12. Comprobación final

```bash
# Verificar que Symfony ve el entorno de producción
APP_ENV=prod php bin/console about

# Verificar que la BD está alcanzable
APP_ENV=prod php bin/console doctrine:query:sql "SELECT 1"

# Comprobar que no hay rutas rotas
APP_ENV=prod php bin/console debug:router
```

Acceder a `https://app.kiwilan.org` en el navegador. Debe aparecer la pantalla de login.

---

## 13. Actualizaciones futuras

Para desplegar una nueva versión:

```bash
cd /home/kiwilan/domains/app.kiwilan.org/manka

git pull origin master

composer install --no-dev --optimize-autoloader --no-interaction

APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console tailwind:build --minify
APP_ENV=prod php bin/console asset-map:compile

# Si hay nuevas dependencias JS
APP_ENV=prod php bin/console importmap:install
```

---

## Resumen de comandos en orden

```bash
git clone https://REPO_URL manka && cd manka

# 1. Dependencias
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Configuración
nano .env.local   # APP_ENV, APP_SECRET, DATABASE_URL, DEFAULT_URI

# 3. BD
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction

# 4. Assets
APP_ENV=prod php bin/console tailwind:build --minify
APP_ENV=prod php bin/console importmap:install
APP_ENV=prod php bin/console asset-map:compile

# 5. Caché
APP_ENV=prod php bin/console cache:warmup

# 6. Primer admin
APP_ENV=prod php bin/console app:create-admin

# 7. Permisos
chown -R www-data:www-data var/ public/assets/

# 8. Configurar Virtualmin: document root → manka/public/
# 9. HTTPS desde panel Virtualmin
```
