#!/bin/bash
echo "📊 MONITOREO PLATAFORMA ESCOLAR"
echo "================================"

while true; do
    clear
    echo "🕐 $(date)"
    echo ""
    
    # Estado del contenedor
    echo "🐳 CONTENEDOR:"
    docker ps --filter "name=plataforma-escolar" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    
    echo ""
    echo "💻 RECURSOS:"
    docker stats plataforma-escolar --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}"
    
    echo ""
    echo "📝 ÚLTIMOS LOGS:"
    docker logs plataforma-escolar --tail 5 --timestamps 2>/dev/null || echo "No hay logs disponibles"
    
    sleep 5
done