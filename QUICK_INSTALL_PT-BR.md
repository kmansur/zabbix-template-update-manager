# Instalação do ZTUM (Português do Brasil)

O instalador oficial do ZTUM é **`install.sh`**, na raiz do repositório.

```bash
cd /usr/local/src
git clone https://github.com/kmansur/zabbix-template-update-manager.git
sudo bash zabbix-template-update-manager/install.sh
```

Para uma verificação sem alterações: `sudo bash zabbix-template-update-manager/install.sh --check`.

Veja [a documentação de instalação](docs/install-from-source.md) para detalhes, requisitos e opções de ambiente.

Os scripts antigos `tools/quickinstall.sh` e `tools/quickinstall-pt-br.sh` foram descontinuados em `main`. Os releases publicados anteriormente permanecem imutáveis. O projeto continua em **beta para laboratório**; não há aprovação para produção.
