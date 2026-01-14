#!/bin/bash
# Script de instalação do mod_audio_stream v1.0.3+
# Ref: https://github.com/amigniter/mod_audio_stream
# 
# Pré-requisitos:
#   - FreeSWITCH já instalado (Debian 12)
#   - Acesso root
#
# Uso:
#   sudo bash install-mod-audio-stream-v103.sh

set -e

echo "=============================================="
echo "  Instalação do mod_audio_stream v1.0.3+"
echo "=============================================="

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar se é root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Erro: Execute este script como root (sudo)${NC}"
    exit 1
fi

# Diretório de trabalho
WORK_DIR="/usr/src/mod_audio_stream"
FS_MOD_DIR="/usr/lib/freeswitch/mod"

# Verificar se FreeSWITCH está instalado
if ! command -v fs_cli &> /dev/null; then
    echo -e "${RED}Erro: FreeSWITCH não encontrado. Instale o FreeSWITCH primeiro.${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Instalando dependências...${NC}"
apt-get update
apt-get install -y git cmake build-essential \
    libfreeswitch-dev libssl-dev zlib1g-dev \
    libevent-dev libspeexdsp-dev pkg-config

echo -e "${YELLOW}2. Fazendo backup do módulo atual (se existir)...${NC}"
if [ -f "$FS_MOD_DIR/mod_audio_stream.so" ]; then
    BACKUP_NAME="mod_audio_stream.so.backup.$(date +%Y%m%d_%H%M%S)"
    cp "$FS_MOD_DIR/mod_audio_stream.so" "$FS_MOD_DIR/$BACKUP_NAME"
    echo -e "${GREEN}   Backup criado: $FS_MOD_DIR/$BACKUP_NAME${NC}"
fi

echo -e "${YELLOW}3. Clonando repositório...${NC}"
rm -rf "$WORK_DIR"
cd /usr/src
git clone https://github.com/amigniter/mod_audio_stream.git
cd mod_audio_stream

echo -e "${YELLOW}4. Inicializando submodules...${NC}"
git submodule init
git submodule update

echo -e "${YELLOW}5. Compilando módulo...${NC}"
mkdir -p build
cd build

# Detectar path do pkgconfig do FreeSWITCH
if [ -d "/usr/local/freeswitch/lib/pkgconfig" ]; then
    export PKG_CONFIG_PATH=/usr/local/freeswitch/lib/pkgconfig
fi

# Compilar com TLS (para wss://)
cmake -DCMAKE_BUILD_TYPE=Release -DUSE_TLS=ON ..
make -j$(nproc)

echo -e "${YELLOW}6. Instalando módulo...${NC}"
make install

# Verificar se instalou corretamente
if [ -f "$FS_MOD_DIR/mod_audio_stream.so" ]; then
    echo -e "${GREEN}   Módulo instalado em: $FS_MOD_DIR/mod_audio_stream.so${NC}"
else
    echo -e "${RED}   Erro: módulo não foi instalado corretamente${NC}"
    exit 1
fi

echo -e "${YELLOW}7. Recarregando módulo no FreeSWITCH...${NC}"

# Verificar se FreeSWITCH está rodando
if systemctl is-active --quiet freeswitch; then
    # Tentar descarregar e recarregar o módulo
    fs_cli -x "unload mod_audio_stream" 2>/dev/null || true
    sleep 1
    fs_cli -x "load mod_audio_stream"
    
    # Verificar se carregou
    if fs_cli -x "module_exists mod_audio_stream" | grep -q "true"; then
        echo -e "${GREEN}   Módulo carregado com sucesso!${NC}"
    else
        echo -e "${RED}   Erro ao carregar módulo. Tentando restart do FreeSWITCH...${NC}"
        systemctl restart freeswitch
        sleep 5
        if fs_cli -x "module_exists mod_audio_stream" | grep -q "true"; then
            echo -e "${GREEN}   Módulo carregado após restart!${NC}"
        else
            echo -e "${RED}   Falha ao carregar módulo. Verifique os logs.${NC}"
            exit 1
        fi
    fi
else
    echo -e "${YELLOW}   FreeSWITCH não está rodando. Inicie manualmente e carregue o módulo.${NC}"
fi

echo ""
echo -e "${GREEN}=============================================="
echo "  Instalação concluída!"
echo "=============================================="
echo ""
echo "Verificação:"
echo "  fs_cli -x \"module_exists mod_audio_stream\""
echo "  fs_cli -x \"show modules\" | grep audio_stream"
echo ""
echo "Variáveis de canal suportadas (v1.0.3+):"
echo "  STREAM_BUFFER_SIZE=200    # buffer em ms (múltiplo de 20)"
echo "  STREAM_SUPPRESS_LOG=true  # silencia logs"
echo "  STREAM_HEART_BEAT=15      # keep-alive em segundos"
echo ""
echo "Exemplo de uso:"
echo "  uuid_audio_stream <uuid> start ws://127.0.0.1:8085/stream/domain/call mono 16k"
echo ""
echo -e "${NC}"
