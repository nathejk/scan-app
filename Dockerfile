FROM alpine:3.5

RUN apk upgrade -U && \
    apk add --update --no-cache \
        bash \
        curl \
        make \
        nginx \
    # web server
        php7-fpm \
        php7-opcache \
    # composer
        php7-json \
        php7-mbstring \
        php7-phar \
        php7-openssl \
        php7-zlib \
    # hirak/prestissimo
        php7-curl \
    # database
        php7-pdo_mysql \
    # Twig
        php7-ctype \
    # phpunit
        php7-dom

# Add S6-overlay to use S6 process manager
# https://github.com/just-containers/s6-overlay/#the-docker-way
ARG S6_VERSION=v1.19.1.1
ENV S6_BEHAVIOUR_IF_STAGE2_FAILS=2
RUN curl -sSL https://github.com/just-containers/s6-overlay/releases/download/${S6_VERSION}/s6-overlay-amd64.tar.gz | tar zxf -

RUN ln -s /usr/bin/php7 /usr/bin/php

WORKDIR /var/www
ENV PATH $PATH:/var/www

# Install app dependencies
COPY composer.* ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --prefer-dist --optimize-autoloader --no-dev && \
    composer clearcache

COPY /rootfs /
COPY . .

EXPOSE 80
ENTRYPOINT ["/init"]
