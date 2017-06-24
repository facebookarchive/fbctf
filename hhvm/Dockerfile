FROM hhvm/hhvm:3.14.5

RUN apt-get update
RUN DEBIAN_FRONTEND=noninteractive apt-get install curl language-pack-en -y --no-install-recommends
RUN /usr/bin/update-alternatives --install /usr/bin/php php /usr/bin/hhvm 60

COPY . /var/www/fbctf

WORKDIR /var/www/fbctf
RUN curl -sS https://getcomposer.org/installer | php
RUN php composer.phar install --no-plugins --no-scripts

COPY hhvm/settings.ini /var/www/fbctf/settings.ini
COPY hhvm/server.ini /etc/hhvm/server.ini

COPY hhvm/docker-entrypoint.sh /usr/bin/
RUN ln -s usr/bin/docker-entrypoint.sh /entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 9000

CMD hhvm -m server -c /etc/hhvm/server.ini
