###
# PokerFamily
#
# Repository:    PHP
# Image:         CLI/Base
# Version:       7.2.x
# Strategy:      PHP From PHP-Alpine Repository with NPM (based image of ambientum/php:7.2-nginx)
# Base distro:   Alpine 3.7
#
FROM ambientum/php:7.2-nginx

# Repository/Image Maintainer
LABEL maintainer="Bruno Gasparin <bgpasparin@gmail.com>"

# Install node
ENV NODE_VERSION 9.11.1

# Go to root to be able to install packages
USER root

# Install PHP From DotDeb, Common Extensions, Composer and then cleanup
RUN echo "---> Installing Libpng for PHP-Alpine" && \
    apk add --update libpng-dev && \
    rm -rf /tmp/*

# Application directory
WORKDIR "/var/www/app"

# Define the running user
USER ambientum

