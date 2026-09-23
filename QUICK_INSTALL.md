# Quick Install

This guide is for users who want to install **Zabbix Template Update Manager (ZTUM)** from scratch with the fewest possible steps.

> **Laboratory beta:** production use is not recommended yet.

## Before you start

You need:

- a Linux server with the Zabbix frontend already installed;
- root or `sudo` access;
- Internet access to GitHub and the official Zabbix source services;
- a Zabbix **Super Admin** account for the final web-interface steps.

The quick installer does **not** change Nginx, Apache, PHP-FPM configuration, the Zabbix database, or any Zabbix template. It only installs the module files and creates the private runtime directories used for backups, offline data and operation locks.

## Recommended installation

Copy and paste these two commands into the Zabbix frontend server:

```bash
curl -fsSL \
  https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/main/tools/quickinstall.sh \
  -o /tmp/ztum-quickinstall.sh

sudo bash /tmp/ztum-quickinstall.sh
```

If `curl` is not available but `wget` is:

```bash
wget -q \
  https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/main/tools/quickinstall.sh \
  -O /tmp/ztum-quickinstall.sh

sudo bash /tmp/ztum-quickinstall.sh
```

The installer will:

1. download the current laboratory beta;
2. validate that the downloaded package contains the required module files;
3. find the Zabbix frontend `modules` directory;
4. detect the PHP-FPM/web runtime user;
5. create the private ZTUM runtime directories with restrictive permissions;
6. install only the files required by the Zabbix frontend module;
7. print the exact final steps to enable the module in Zabbix.

If more than one Zabbix modules directory or PHP-FPM user is detected, the installer will show the available choices instead of guessing.

## Enable the module in Zabbix

After the script finishes:

1. Sign in to Zabbix as **Super Admin**.
2. Open **Administration → General → Modules**.
3. Click **Scan directory**.
4. Find **Template Update Manager**.
5. Confirm the displayed version and enable the module.
6. Open **Data collection → Template updates**.

## Advanced options

Show installer help:

```bash
sudo bash /tmp/ztum-quickinstall.sh --help
```

Install a specific branch, tag or commit:

```bash
sudo bash /tmp/ztum-quickinstall.sh --ref main
```

Specify the Zabbix modules directory manually:

```bash
sudo bash /tmp/ztum-quickinstall.sh \
  --modules-dir /usr/share/zabbix/modules
```

Specify the PHP-FPM/web user manually:

```bash
sudo bash /tmp/ztum-quickinstall.sh \
  --php-user www-data
```

For non-interactive automation, provide all ambiguous values explicitly and add `--yes`.

## Existing installation

The quick installer is intentionally **new-install only**. If it finds an existing directory named:

```text
zabbix-template-update-manager
```

it stops instead of overwriting it.

This prevents an inexperienced user from accidentally replacing a working installation or its local state.

## If the module does not appear

First run **Scan directory** again. If it still does not appear, restart the PHP-FPM service used by the Zabbix frontend and scan again.

Examples:

```bash
sudo systemctl restart php8.2-fpm
```

or:

```bash
sudo systemctl restart php-fpm
```

The exact service name depends on the operating system and PHP version.

For detailed installation and validation information, see [README.md](README.md), [docs/runtime-setup.md](docs/runtime-setup.md) and [docs/lab-test-plan.md](docs/lab-test-plan.md).
