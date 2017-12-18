FROM ubuntu:xenial

ENV HOME /root

ARG DOMAIN
ARG EMAIL
ARG MODE=dev
ARG TYPE=self
ARG KEY
ARG CRT

WORKDIR $HOME
COPY . $HOME

RUN apt-get update && apt-get -y install sudo apt-utils
RUN ./extra/provision.sh -m $MODE -c $TYPE -k $KEY -C $CRT -D $DOMAIN -e $EMAIL -s `pwd` --docker --multiple-servers --server-type mysql
CMD ["./extra/mysql/mysql_startup.sh"]
