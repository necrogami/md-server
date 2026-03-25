ARG BUILDER_IMAGE=dunglas/frankenphp:static-builder-musl
ARG PLATFORM=linux/amd64

FROM --platform=${PLATFORM} ${BUILDER_IMAGE}

# Copy app source
WORKDIR /go/src/app/dist/app
COPY . .

# Install production dependencies
RUN composer install --ignore-platform-reqs --no-dev --classmap-authoritative --no-scripts

# Remove dev/test artifacts to reduce binary size
RUN rm -rf tests/ docs/ tools/ .github/ .claude/ \
    phpunit.xml phpunit.xml.dist static-build.Dockerfile \
    .git/ .gitignore

# Build the static binary
WORKDIR /go/src/app/
RUN EMBED=dist/app/ \
    PHP_EXTENSIONS="mbstring,xml,dom,ctype,iconv,tokenizer,filter,phar,zlib,opcache,simplexml,yaml" \
    ./build-static.sh
