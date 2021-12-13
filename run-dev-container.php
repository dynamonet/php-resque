#!/bin/bash

docker run -it --rm -w /home/app -v $PWD:/home/app \
   -e REDIS_HOST=host.docker.internal \
   dynamonet/php:7.3 bash
