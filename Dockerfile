FROM ubuntu:trusty
MAINTAINER Boik boik@tdohacker.org

ENV HOME /root

ARG DOMAIN
ARG EMAIL
ARG MODE=dev
ARG TYPE=self
ARG KEY
ARG CRT

WORKDIR $HOME
COPY . $HOME
RUN apt-get update \
  && apt-get install -y \
  rsync \
  curl \
  ca-certificates \
  && chown www-data:www-data $HOME \
  && ./extra/provision.sh -m $MODE -c $TYPE -k $KEY -C $CRT -D $DOMAIN -e $EMAIL -s `pwd` --docker \
  && rm -f /var/run/hhvm/sock \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
CMD ["./extra/service_startup.sh"]
