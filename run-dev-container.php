#!/bin/bash

docker run -it --rm -w /home/app -v $PWD:/home/app dynamonet/php73 bash
