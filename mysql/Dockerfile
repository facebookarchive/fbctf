FROM mysql:5.5

RUN apt-get update
RUN apt-get install php5 -y --no-install-recommends

COPY database/schema.sql /docker-entrypoint-initdb.d/aschema.sql
COPY ["database/countries.sql", "database/logos.sql",  "/docker-entrypoint-initdb.d/"]
COPY extra/hash.php /
COPY mysql/docker-entrypoint.sh /usr/local/bin/

ENV MYSQL_DATABASE=fbctf MYSQL_USER=ctf MYSQL_PASSWORD=ctf
