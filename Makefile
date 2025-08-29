build: 
	docker build . --tag image-pubsub && make first-run
rebuild:
	docker build --no-cache . --tag image-pubsub && make first-run
first-run:
	docker run -p 9000:80 --name container-pubsub --mount type=bind,source=./pubsub,target=/var/www/html/pubsub image-pubsub

start:
	docker start container-pubsub
stop:
	docker stop container-pubsub

ssh:
	docker exec -it container-pubsub /bin/bash