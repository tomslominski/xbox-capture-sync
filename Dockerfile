FROM php:8.1-cli
COPY index.php /usr/src/xbox-capture-sync/index.php
WORKDIR /usr/src/xbox-capture-sync
CMD [ "php", "-S", "0.0.0.0:80" ]
