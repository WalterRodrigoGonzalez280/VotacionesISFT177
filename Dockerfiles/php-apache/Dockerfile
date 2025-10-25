FROM php:7.4-apache

RUN docker-php-ext-install mysqli
RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_mysql

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get update && \
	apt-get upgrade -y && \
	apt-get install -y git

RUN echo "date.timezone = America/Argentina/Buenos_Aires" > /usr/local/etc/php/php.ini
RUN echo "session.cookie_lifetime=86400" >> /usr/local/etc/php/php.ini
RUN echo "session.gc_maxlifetime=86400" >> /usr/local/etc/php/php.ini

