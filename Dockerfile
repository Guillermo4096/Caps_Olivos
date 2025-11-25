FROM php:8.1-apache

# Metadatos
LABEL maintainer="TuNombre"
LABEL description="Plataforma de Gestión de Tareas Escolares"
LABEL version="1.0"

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    mbstring \
    xml \
    zip

# Habilitar módulos de Apache
RUN a2enmod rewrite
RUN a2enmod headers

# Configurar PHP
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/custom.ini

# Crear directorio de la aplicación
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html -type f -exec chmod 644 {} \;

# Salud check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Exponer puerto
EXPOSE 80

# Comando de inicio
CMD ["apache2-foreground"]