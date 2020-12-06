@echo off 
set CAMPAIGN_ID=15641 
set DATADIR=d:/data
docker run -it --rm -v %CD%:/usr/src/resque -w /usr/src/resque --network="host" --name=resque-dev dynamonet/php73 /bin/bash
pause
