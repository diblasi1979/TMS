FROM node:22-alpine

COPY docker/start.sh /usr/local/bin/start-frontend

RUN chmod +x /usr/local/bin/start-frontend

WORKDIR /app

EXPOSE 5173

CMD ["/usr/local/bin/start-frontend"]