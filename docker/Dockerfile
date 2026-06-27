# =====================================================================
# Imagem de desenvolvimento local (NAO usada em producao/Hostinger).
# PHP 8.2 + Apache, com PDO MySQL e mod_rewrite, document root em /public.
# =====================================================================
FROM php:8.2-apache

# Extensoes PHP necessarias
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

# Document root aponta para /public (apenas essa pasta fica exposta)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Configura o vhost para usar /public e permitir .htaccess (AllowOverride All)
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# Evita o aviso "Could not reliably determine the server's fully qualified domain name"
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
