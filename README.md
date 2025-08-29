# Test pubsub with Websocket and PHP

## Prerequisite
Install make and docker

## Setup the environment
- launch "make docker-init"  
- launch "make project-init"  
- launch "make launch-server"  

## Listen and Publish
Terminal 1:
- type "make listen"  

Terminal 2:
- type "make publish"

![Example](example.png)

## Start and stop the container
- launch "make start"  
- launch "make stop"  

## Issues doing a realtime based system with PHP
PHP is a monothreaded language and can't listen and publish in the same instance. And all attempts to fake routines and threads result to a complex architecture which is not resilient and stable for production purposes. This test is made with PHP as I have a most advanced knowledge on this language. But it is not something I would consider for a production environment. I would tend to recommend compiled languages multithreaded languages like Rust, Go, Java.  

## Should be taken into consideration
Compared to the Golang test, I had to setup all the stack, create the environment from scratch, facilitate the deployment, and write the full code to make it run. It was not only a matter of coding the handler. With the same timeframe, the quality of code may be impacted, and the unit tests missing.