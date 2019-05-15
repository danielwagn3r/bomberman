FROM whatwedo/nginx-php70:latest

ADD . /var/www
WORKDIR /var/www

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar global require hirak/prestissimo&& \
    php composer.phar install --no-scripts --prefer-dist && \
    php composer.phar dump-autoload --optimize && \
    mv app/docker/supervisord.conf /etc/supervisor/conf.d/bomberman.conf && \
    cp app/docker/nginx.conf /etc/nginx/nginx.conf && \
    echo 'chown -R www-data:www-data /var/www && chmod -R 755 /var/www' >> /bin/everyboot

EXPOSE 8080

RUN chgrp -R 0 /var/www && \
    chmod -R g=u /var/www
    
### user name recognition at runtime w/ an arbitrary uid - for OpenShift deployments
ENTRYPOINT [ "uid_entrypoint" ]
