FROM olbat/cupsd

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
    && apt-get install -y \
    php \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord"]
#CMD ["/usr/sbin/runsvdir-start"]