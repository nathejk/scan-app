FROM ubuntu:16.04

WORKDIR /var/www

RUN apt-get update && \

    # Hiawatha
    echo "deb http://ppa.launchpad.net/octavhendra/hiawatha/ubuntu xenial main" > /etc/apt/sources.list.d/hiawatha.list && \
    apt install -y add-apt-key && \
    add-apt-key --keyserver keyserver.ubuntu.com 0x7813b17e4f41a0a569f421e04dab5457dac7eb24 && \

    apt update && \
    apt install -y -q --no-install-recommends composer hiawatha php-bcmath php-cli php-curl php-fpm php-mysql php-xml php-zip supervisor && \
    apt clean && \
    rm -rf /var/lib/apt/lists/*

COPY etc/hiawatha.conf /etc/hiawatha/hiawatha.conf

RUN echo "variables_order = EGPCS" > /etc/php/7.0/fpm/conf.d/99-env.ini
RUN echo "variables_order = EGPCS" > /etc/php/7.0/cli/conf.d/99-env.ini

# Install app dependencies
COPY composer.* ./
RUN composer install --prefer-dist && \
    composer clearcache

ENV PATH $PATH:/var/www:/var/www/vendor/bin

COPY . .

# Test image
#RUN ./vendor/bin/phpunit src

EXPOSE 80
CMD supervisord -c etc/supervisord.conf
