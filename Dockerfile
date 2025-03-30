FROM alpine:3.21.3

RUN apk add --update \
    && apk cache clean \
    && rm -rf /var/cache/apk/* \
    && apk del --purge \
    && rm -rf /tmp/* /var/tmp/* \
    && find /var/log -type f -delete

RUN apk add --no-cache curl php84-fpm php84 php84-json php84-pdo php84-pdo_mysql php84-sockets php84-pcntl php84-posix

RUN ln -s /usr/bin/php84 /usr/bin/php

WORKDIR '/var/www/html'

CMD ["php-fpm", "-F"]

RUN apk cache clean \
    && rm -rf /var/cache/apk/* \
    && apk del --purge \
    && rm -rf /tmp/* /var/tmp/* \
    && find /var/log -type f -delete