docker-init:
	docker build --no-cache . --tag image-pubsub && docker run -d -p 9000:80 --name container-pubsub --mount type=bind,source=./pubsub,target=/var/www/html/pubsub image-pubsub

project-init:
	docker exec -it container-pubsub composer install --working-dir /var/www/html/pubsub

launch-server:
	docker exec -it container-pubsub php /var/www/html/pubsub/server/server.php

listen:
	docker exec -it container-pubsub php /var/www/html/pubsub/console.php listener

publish:
	docker exec -it container-pubsub php /var/www/html/pubsub/console.php publisher

start:
	docker start container-pubsub

stop:
	docker stop container-pubsub

ssh:
	docker exec -it container-pubsub /bin/bash