#!/bin/bash
set -e

echo "🚀 Iniciando despliegue de la plataforma escolar..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Función para limpiar
cleanup() {
    echo -e "${YELLOW}🛑 Deteniendo contenedor existente...${NC}"
    docker stop plataforma-escolar 2>/dev/null || true
    docker rm plataforma-escolar 2>/dev/null || true
}

# Función para construir
build_image() {
    echo -e "${YELLOW}🔨 Construyendo imagen...${NC}"
    docker build -t plataforma-escolar .
}

# Función para ejecutar
run_container() {
    echo -e "${YELLOW}🐳 Ejecutando contenedor...${NC}"
    docker run -d \
        --name plataforma-escolar \
        -p 8080:80 \
        --restart unless-stopped \
        --memory="1g" \
        --cpus="1.0" \
        plataforma-escolar
}

# Ejecutar pasos
cleanup
build_image
run_container

echo -e "${GREEN}✅ Despliegue completado!${NC}"
echo -e "${GREEN}🌐 Accede en: http://localhost:8080${NC}"
echo -e "${GREEN}📊 Monitoreo: docker logs -f plataforma-escolar${NC}"