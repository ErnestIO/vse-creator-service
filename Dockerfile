FROM ubuntu:latest
MAINTAINER Raül Pérez <raul.perez@r3labs.io>
RUN apt-get update
RUN apt-get upgrade -y
RUN apt-get install -y make git wget php5 php5-cli
WORKDIR /vse
VOLUME ["/vse"]
EXPOSE 8080
CMD ["/usr/bin/php", "-S", "localhost:8080"]
