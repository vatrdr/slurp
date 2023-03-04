FROM php:8.2.0-cli-alpine

# upgrade alpine
RUN apk -U upgrade

WORKDIR /app
