EXPOSE 80FROM php:8.2-apache
RUN echo "nameserver 8.8.8.8" > /etc/resolv.conf
RUN echo "nameserver 1.1.1.1" >> /etc/resolv.conf
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/
# ... (le reste de votre Dockerfile)

