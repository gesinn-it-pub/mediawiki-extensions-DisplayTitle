-include .env
export

# setup for docker-compose-ci build directory
# delete "build" directory to update docker-compose-ci

ifeq (,$(wildcard ./build/Makefile))
    $(shell git submodule update --init --remote)
endif


EXTENSION=DisplayTitle

# docker images
MW_VERSION?=1.43
PHP_VERSION?=8.3
DB_TYPE?=mysql
DB_IMAGE?=mysql:8.0

# extensions
SCRIBUNTO_VERSION?=REL1_43

# composer
# Enables "composer update" inside of extension
COMPOSER_EXT?=true

# Enables node.js related tests and "npm install"
NODE_JS?=true


include build/Makefile
