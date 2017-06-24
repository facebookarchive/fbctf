FROM nginx:1.10.2

RUN rm -f /etc/nginx/sites-enabled/default
RUN apt-get update
RUN DEBIAN_FRONTEND=noninteractive apt-get install npm ocaml libelf-dev nodejs-legacy -y --no-install-recommends
RUN npm install -g npm@lts

COPY . /var/www/fbctf/
WORKDIR /var/www/fbctf
RUN npm install
RUN npm install -g grunt flow-bin
RUN grunt --force

COPY nginx/fbctf.conf /etc/nginx/sites-enabled/fbctf.conf
COPY nginx/nginx.conf /etc/nginx/nginx.conf
COPY nginx/docker-entrypoint.sh /usr/bin/
RUN ln -s usr/bin/docker-entrypoint.sh /entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
