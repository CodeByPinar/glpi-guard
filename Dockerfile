# GLPI Guard — command-line scanner as a container image (ghcr.io/codebypinar/glpi-guard)
#
#   docker run --rm --network host -v /var/www/glpi:/glpi:ro ghcr.io/codebypinar/glpi-guard scan /glpi
#
# Mount the GLPI directory read-only. --network host lets the container reach a database listening on localhost.
FROM php:8.3-cli-alpine

RUN docker-php-ext-install mysqli \
    && rm -rf /tmp/* /usr/src/php*

ENV GLPI_GUARD_RUNTIME=docker

WORKDIR /opt/glpi-guard
COPY bin/ bin/
COPY src/ src/
COPY data/ data/
COPY LICENSE README.md ./
RUN chmod 0755 bin/glpi-guard \
    && ln -s /opt/glpi-guard/bin/glpi-guard /usr/local/bin/glpi-guard \
    && glpi-guard --version

LABEL org.opencontainers.image.title="GLPI Guard" \
      org.opencontainers.image.description="Read-only security audit for your own GLPI installation" \
      org.opencontainers.image.source="https://github.com/CodeByPinar/glpi-guard" \
      org.opencontainers.image.licenses="GPL-3.0-or-later"

ENTRYPOINT ["glpi-guard"]
CMD ["--help"]
