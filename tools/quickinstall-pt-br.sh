#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY="kmansur/zabbix-template-update-manager"
REF="${ZTUM_REF:-main}"
MODULES_DIR=""
PHP_USER=""
ASSUME_YES=0
TARGET_NAME="zabbix-template-update-manager"
RUNTIME_BASE="/var/lib/zabbix-template-update-manager"

info() { printf '[ZTUM] %s\n' "$*"; }
warn() { printf '[ZTUM] AVISO: %s\n' "$*" >&2; }
die() { printf '[ZTUM] ERRO: %s\n' "$*" >&2; exit 1; }

usage() {
  cat <<'EOF'
ZTUM Quick Install (Português do Brasil)

Uso:
  sudo bash tools/quickinstall-pt-br.sh [opções]

Opções:
  --ref REF             Branch/tag/commit Git a instalar (padrão: main)
  --modules-dir DIR     Diretório de módulos do frontend Zabbix
  --php-user USER       Usuário de execução do PHP-FPM/web
  --yes                 Modo não interativo
  -h, --help            Exibe esta ajuda

Este instalador é destinado a uma NOVA instalação de laboratório.
Ele se recusa a sobrescrever uma instalação ZTUM existente.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ref) REF="${2:-}"; shift 2 ;;
    --modules-dir) MODULES_DIR="${2:-}"; shift 2 ;;
    --php-user) PHP_USER="${2:-}"; shift 2 ;;
    --yes) ASSUME_YES=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) die "Opção desconhecida: $1" ;;
  esac
done

[[ -n "$REF" ]] || die "--ref não pode ficar vazio."

if [[ ${EUID:-$(id -u)} -ne 0 ]]; then
  die "Execute este instalador como root, por exemplo: sudo bash tools/quickinstall-pt-br.sh"
fi

for command in tar find install cp chmod chown sort awk grep sed mktemp xargs; do
  command -v "$command" >/dev/null 2>&1 || die "Comando obrigatório não encontrado: $command"
done

if command -v curl >/dev/null 2>&1; then
  DOWNLOADER="curl"
elif command -v wget >/dev/null 2>&1; then
  DOWNLOADER="wget"
else
  die "É necessário ter curl ou wget. Instale um deles e execute novamente."
fi

TMP_DIR="$(mktemp -d -t ztum-quickinstall.XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT
mkdir -p "$TMP_DIR/extract"

if [[ "$REF" =~ ^[0-9a-fA-F]{40}$ ]]; then
  ARCHIVE_URL="https://github.com/${REPOSITORY}/archive/${REF}.tar.gz"
elif [[ "$REF" == v* ]]; then
  ARCHIVE_URL="https://github.com/${REPOSITORY}/archive/refs/tags/${REF}.tar.gz"
else
  ARCHIVE_URL="https://github.com/${REPOSITORY}/archive/refs/heads/${REF}.tar.gz"
fi

info "Baixando o código do ZTUM: $REF"
if [[ "$DOWNLOADER" == "curl" ]]; then
  curl --fail --location --silent --show-error     --connect-timeout 10 --max-time 120     "$ARCHIVE_URL" -o "$TMP_DIR/ztum.tar.gz"     || die "Não foi possível baixar $ARCHIVE_URL"
else
  wget -q --timeout=120 -O "$TMP_DIR/ztum.tar.gz" "$ARCHIVE_URL"     || die "Não foi possível baixar $ARCHIVE_URL"
fi

tar -xzf "$TMP_DIR/ztum.tar.gz" -C "$TMP_DIR/extract"
SOURCE_DIR="$(find "$TMP_DIR/extract" -mindepth 1 -maxdepth 1 -type d -print -quit)"
[[ -n "$SOURCE_DIR" ]] || die "Não foi possível extrair o arquivo baixado."

for required in Module.php manifest.json VERSION actions src views tools/ztum-runtime-setup.sh; do
  [[ -e "$SOURCE_DIR/$required" ]] || die "O pacote baixado está incompleto: falta $required"
done

VERSION="$(tr -d '\r\n' < "$SOURCE_DIR/VERSION")"
[[ -n "$VERSION" ]] || die "O arquivo VERSION está vazio."
info "Versão baixada: $VERSION"

choose_modules_dir() {
  local candidates=()
  local candidate

  for candidate in     /usr/share/zabbix/modules     /usr/share/zabbix/ui/modules     /usr/local/share/zabbix/modules     /usr/local/share/zabbix/ui/modules     /var/www/html/zabbix/modules     /var/www/zabbix/modules; do
    [[ -d "$candidate" ]] && candidates+=("$candidate")
  done

  while IFS= read -r candidate; do
    [[ -n "$candidate" ]] && candidates+=("$candidate")
  done < <(
    find /usr/share/zabbix /usr/local/share/zabbix /var/www       -maxdepth 6 -type d -name modules 2>/dev/null || true
  )

  mapfile -t candidates < <(printf '%s\n' "${candidates[@]}" | awk 'NF' | sort -u)

  if [[ ${#candidates[@]} -eq 0 ]]; then
    die "Nenhum diretório de módulos do frontend Zabbix foi encontrado. Execute novamente com --modules-dir /caminho/do/zabbix/modules"
  fi

  if [[ ${#candidates[@]} -eq 1 ]]; then
    MODULES_DIR="${candidates[0]}"
    return
  fi

  printf '\nFoi encontrado mais de um diretório de módulos do Zabbix:\n'
  local i=1
  for candidate in "${candidates[@]}"; do
    printf '  %d) %s\n' "$i" "$candidate"
    ((i++))
  done

  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
    die "Há vários diretórios de módulos. Execute novamente informando --modules-dir DIR."
  fi

  local choice
  read -r -p "Escolha o diretório de módulos do Zabbix [1-${#candidates[@]}]: " choice
  [[ "$choice" =~ ^[0-9]+$ ]] || die "Seleção inválida."
  (( choice >= 1 && choice <= ${#candidates[@]} )) || die "Seleção inválida."
  MODULES_DIR="${candidates[choice-1]}"
}

if [[ -z "$MODULES_DIR" ]]; then
  choose_modules_dir
fi
[[ -d "$MODULES_DIR" ]] || die "O diretório de módulos não existe: $MODULES_DIR"

TARGET_DIR="$MODULES_DIR/$TARGET_NAME"
if [[ -e "$TARGET_DIR" ]]; then
  die "O ZTUM já existe em $TARGET_DIR. Este quick installer realiza apenas instalação nova e não sobrescreve uma instalação existente."
fi

detect_php_user() {
  local pool_files=()
  local users=()
  local file value

  shopt -s nullglob
  pool_files=(
    /etc/php/*/fpm/pool.d/*zabbix*.conf
    /etc/php-fpm.d/*zabbix*.conf
    /usr/local/etc/php-fpm.d/*zabbix*.conf
  )
  shopt -u nullglob

  if [[ ${#pool_files[@]} -eq 0 ]]; then
    shopt -s nullglob
    pool_files=(
      /etc/php/*/fpm/pool.d/*.conf
      /etc/php-fpm.d/*.conf
      /usr/local/etc/php-fpm.d/*.conf
    )
    shopt -u nullglob
  fi

  for file in "${pool_files[@]}"; do
    while IFS= read -r value; do
      value="${value%%;*}"
      value="${value#*=}"
      value="$(printf '%s' "$value" | xargs)"
      [[ -n "$value" && "$value" != "root" ]] && users+=("$value")
    done < <(grep -E '^[[:space:]]*user[[:space:]]*=' "$file" 2>/dev/null || true)
  done

  mapfile -t users < <(printf '%s\n' "${users[@]}" | awk 'NF' | sort -u)

  if [[ ${#users[@]} -eq 1 ]]; then
    PHP_USER="${users[0]}"
    return
  fi

  if [[ ${#users[@]} -gt 1 ]]; then
    printf '\nFoi encontrado mais de um usuário de execução do PHP-FPM:\n'
    local i=1
    for value in "${users[@]}"; do
      printf '  %d) %s\n' "$i" "$value"
      ((i++))
    done

    if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
      die "Foram encontrados vários usuários PHP-FPM. Execute novamente informando --php-user USER."
    fi

    local choice
    read -r -p "Escolha o usuário do PHP-FPM [1-${#users[@]}]: " choice
    [[ "$choice" =~ ^[0-9]+$ ]] || die "Seleção inválida."
    (( choice >= 1 && choice <= ${#users[@]} )) || die "Seleção inválida."
    PHP_USER="${users[choice-1]}"
    return
  fi

  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
    die "Não foi possível detectar o usuário do PHP-FPM. Execute novamente informando --php-user USER."
  fi

  read -r -p "Usuário do PHP-FPM/web (exemplos comuns: www-data, apache, nginx): " PHP_USER
}

if [[ -z "$PHP_USER" ]]; then
  detect_php_user
fi
id "$PHP_USER" >/dev/null 2>&1 || die "O usuário de sistema não existe: $PHP_USER"

info "Diretório de módulos do Zabbix: $MODULES_DIR"
info "Usuário do PHP-FPM/web: $PHP_USER"
info "Preparando os diretórios privados de runtime."
bash "$SOURCE_DIR/tools/ztum-runtime-setup.sh" --apply --user "$PHP_USER" --base "$RUNTIME_BASE"
bash "$SOURCE_DIR/tools/ztum-runtime-setup.sh" --check --user "$PHP_USER" --base "$RUNTIME_BASE"

info "Instalando o módulo."
install -d -o root -g root -m 0755 "$TARGET_DIR"
cp -a   "$SOURCE_DIR/Module.php"   "$SOURCE_DIR/manifest.json"   "$SOURCE_DIR/VERSION"   "$SOURCE_DIR/actions"   "$SOURCE_DIR/src"   "$SOURCE_DIR/views"   "$TARGET_DIR/"

chown -R root:root "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 0755 {} +
find "$TARGET_DIR" -type f -exec chmod 0644 {} +

[[ -r "$TARGET_DIR/manifest.json" && -r "$TARGET_DIR/Module.php" ]]   || die "A verificação da instalação falhou."

printf '\n'
info "ZTUM $VERSION instalado com sucesso."
printf '\nPróximos passos na interface web do Zabbix:\n'
printf '  1. Entre no Zabbix como Super Admin.\n'
printf '  2. Acesse: Administração -> Geral -> Módulos.\n'
printf '  3. Clique em: Scan directory / Examinar diretório.\n'
printf '  4. Habilite: Template Update Manager.\n'
printf '  5. Acesse: Coleta de dados -> Template updates.\n'
printf '\n'
warn "Esta é uma versão beta de laboratório. O uso em produção ainda não é recomendado."
printf 'Se o módulo não aparecer após a varredura, reinicie o serviço PHP-FPM e execute a varredura novamente.\n'
printf 'Caminho instalado: %s\n' "$TARGET_DIR"
printf 'Caminho de runtime: %s\n' "$RUNTIME_BASE"
