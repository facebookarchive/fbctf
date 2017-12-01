FROM ubuntu:xenial

ENV HOME /root

ARG DOMAIN
ARG EMAIL
ARG MODE=dev
ARG TYPE=self
ARG KEY
ARG CRT

ENV HHVM_DISABLE_NUMA true

WORKDIR $HOME
COPY . $HOME

RUN apt-get update && apt-get -y install sudo apt-utils
RUN ./extra/provision.sh -m $MODE -c $TYPE -k $KEY -C $CRT -D $DOMAIN -e $EMAIL -s `pwd` --docker --multiple-servers --server-type hhvm --mysql-server mysql --cache-server cache
CMD ["./extra/hhvm/hhvm_startup.sh"]
