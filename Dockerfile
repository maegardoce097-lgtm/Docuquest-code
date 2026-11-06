ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev libonig-dev libpng-dev unzip \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install curl gd mbstring pdo_mysql \
	&& a2enmod rewrite headers \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
	PORT=10000

WORKDIR /var/www/html
COPY . /var/www/html/

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
	&& sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
	&& sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
	&& mkdir -p runtime/logs runtime/uploads \
	&& chown -R www-data:www-data /var/www/html \
	&& chmod -R 755 /var/www/html \
	&& chmod -R 775 runtime

COPY docker-entrypoint.sh /usr/local/bin/docuquest-entrypoint
RUN chmod 755 /usr/local/bin/docuquest-entrypoint

EXPOSE 10000
ENTRYPOINT ["docuquest-entrypoint"]
CMD ["apache2-foreground"]