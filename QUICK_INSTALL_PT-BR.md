# Instalação Rápida

Este guia é para quem deseja instalar o **Zabbix Template Update Manager (ZTUM)** do zero com o menor número possível de etapas.

> **Beta de laboratório:** o uso em produção ainda não é recomendado.

## Antes de começar

Você precisa de:

- um servidor Linux com o frontend do Zabbix já instalado;
- acesso `root` ou `sudo`;
- acesso à Internet para o GitHub e os serviços oficiais de código-fonte do Zabbix;
- uma conta **Super Admin** no Zabbix para as etapas finais na interface web.

O quick installer **não** altera Nginx, Apache, configuração do PHP-FPM, banco de dados do Zabbix ou templates do Zabbix. Ele apenas instala os arquivos do módulo e cria os diretórios privados usados para backups, dados offline e locks de operação.

## Instalação recomendada

Copie e cole estes dois comandos no servidor que executa o frontend do Zabbix:

```bash
curl -fsSL \
  https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/main/tools/quickinstall-pt-br.sh \
  -o /tmp/ztum-quickinstall-pt-br.sh

sudo bash /tmp/ztum-quickinstall-pt-br.sh
```

Se não houver `curl`, mas houver `wget`:

```bash
wget -q \
  https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/main/tools/quickinstall-pt-br.sh \
  -O /tmp/ztum-quickinstall-pt-br.sh

sudo bash /tmp/ztum-quickinstall-pt-br.sh
```

O instalador irá:

1. baixar a versão beta de laboratório atual;
2. validar se o pacote contém os arquivos obrigatórios do módulo;
3. localizar o diretório `modules` do frontend Zabbix;
4. detectar o usuário de execução do PHP-FPM/web;
5. criar os diretórios privados de runtime do ZTUM com permissões restritivas;
6. instalar apenas os arquivos necessários para o módulo do frontend;
7. mostrar exatamente o que deve ser feito na interface do Zabbix para habilitar o módulo.

Se houver mais de um diretório de módulos do Zabbix ou mais de um usuário PHP-FPM, o instalador mostrará as opções disponíveis em vez de escolher sozinho.

## Habilitar o módulo no Zabbix

Depois que o script terminar:

1. Entre no Zabbix como **Super Admin**.
2. Acesse **Administração → Geral → Módulos**.
3. Clique em **Scan directory / Examinar diretório**.
4. Localize **Template Update Manager**.
5. Confira a versão exibida e habilite o módulo.
6. Acesse **Coleta de dados → Template updates**.

## Opções avançadas

Exibir a ajuda:

```bash
sudo bash /tmp/ztum-quickinstall-pt-br.sh --help
```

Instalar uma branch, tag ou commit específico:

```bash
sudo bash /tmp/ztum-quickinstall-pt-br.sh --ref main
```

Informar manualmente o diretório de módulos do Zabbix:

```bash
sudo bash /tmp/ztum-quickinstall-pt-br.sh \
  --modules-dir /usr/share/zabbix/modules
```

Informar manualmente o usuário do PHP-FPM/web:

```bash
sudo bash /tmp/ztum-quickinstall-pt-br.sh \
  --php-user www-data
```

Para automação não interativa, informe explicitamente todos os valores que possam ser ambíguos e adicione `--yes`.

## Instalação existente

O quick installer foi feito, de propósito, **somente para instalação nova**. Se ele encontrar um diretório existente chamado:

```text
zabbix-template-update-manager
```

ele interrompe a execução em vez de sobrescrevê-lo.

Isso evita que um usuário menos experiente substitua acidentalmente uma instalação funcional ou algum estado local.

## Se o módulo não aparecer

Primeiro execute novamente **Scan directory / Examinar diretório**. Se ainda assim o módulo não aparecer, reinicie o serviço PHP-FPM usado pelo frontend do Zabbix e faça a varredura novamente.

Exemplos:

```bash
sudo systemctl restart php8.2-fpm
```

ou:

```bash
sudo systemctl restart php-fpm
```

O nome exato do serviço depende do sistema operacional e da versão do PHP.

Para informações detalhadas sobre instalação e validação, consulte [README.md](README.md), [docs/runtime-setup.md](docs/runtime-setup.md) e [docs/lab-test-plan.md](docs/lab-test-plan.md).
