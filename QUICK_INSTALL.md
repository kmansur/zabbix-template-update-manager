# Installing ZTUM (English)

The canonical ZTUM installer is the repository root **`install.sh`**.

```bash
cd /usr/local/src
git clone https://github.com/kmansur/zabbix-template-update-manager.git
sudo bash zabbix-template-update-manager/install.sh
```

For read-only diagnostics: `sudo bash zabbix-template-update-manager/install.sh --check`.

See [the detailed install guide](docs/install-from-source.md) for requirements, checks and overrides.

The legacy `tools/quickinstall.sh` and `tools/quickinstall-pt-br.sh` scripts are retired from `main`. Previously published tagged releases remain immutable. The project is a **laboratory beta**, not production approved.
