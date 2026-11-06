# Reference image only: runs the frozen wpkg-express-ng code to capture golden XML feeds.
# Never deploy this image.
FROM php:5.6-apache
RUN a2enmod rewrite && docker-php-ext-install mysqli
RUN printf 'date.timezone=UTC\ndisplay_errors=0\nlog_errors=1\nerror_log=/proc/self/fd/2\n' > /usr/local/etc/php/conf.d/legacy.ini
COPY wpkg-express-ng/ /var/www/html/
COPY config/ /var/www/html/config/
WORKDIR /var/www/html
RUN sed -i.bak 's/error_reporting(E_ALL \& ~E_DEPRECATED);/error_reporting(E_ALL \& ~E_STRICT \& ~E_DEPRECATED);/' cake/bootstrap.php \
 && sed -i "s/Configure::write('Security.salt', '');/Configure::write('Security.salt', 'legacyfixturesaltlegacyfixturesalt0123456');/" config/core.php \
 && sed -i "s/Configure::write('Security.cipherSeed', '');/Configure::write('Security.cipherSeed', '1234567890123456789012345678');/" config/core.php \
 && touch do_not_remove \
 && chown -R www-data:www-data /var/www/html
