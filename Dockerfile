FROM webdevops/php-nginx:8.1

ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_DISMOD=bz2,calendar,exiif,ffi,intl,gettext,ldap,mysqli,imap,pdo_pgsql,pgsql,soap,sockets,sysvmsg,sysvsm,sysvshm,shmop,xsl,zip,gd,apcu,vips,yaml,imagick,mongodb,amqp

RUN echo "extension=mongodb.so" >> /opt/docker/etc/php/php.ini

WORKDIR /app

COPY . .
RUN composer install --no-interaction --optimize-autoloader

RUN chown -R application:application .
