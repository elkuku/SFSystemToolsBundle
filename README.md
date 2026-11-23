# Symfony System Tools

A small Symfony bundle with system pages for a project:

- **`/system/info/`**: the Symfony and PHP versions as JSON, for monitoring tools such as the KuKu project checker.
- **About** (optional): the output of the `about` console command.
- **Deploy log** (optional): the runs of `bin/deploy` from its log file. A calendar marks the days with runs in the color
  of their status (deployed, finished with errors, did not finish); next to it are the details of one run, the newest by
  default: when it ran, the other runs of that day, and its output split into the deploy steps, with `[OK]` lines in green
  and errors in red.

## Installation

```shell
composer require elkuku/kuku-sf-system-tools-bundle
```

## System info

`config/routes/kuku_sfsysteminfo.yaml`:

```yaml
_kuku_sfsysinfo:
  resource: '@KuKuSFSystemToolsBundle/config/routes.yaml'
  prefix: /system/info
```

The versions are hidden (`N/A`) unless you expose them in `config/packages/kuku_sfsysteminfo.yaml`:

```yaml
kuku_sf_system_tools:
  expose_sf_version: true
  expose_php_version: true
```

## About and deploy log pages

They need `symfony/twig-bundle` and `symfony/security-bundle` (and `symfony/console` for the About page).
Enable them in the bundle configuration; all settings are optional:

```yaml
kuku_sf_system_tools:
  admin:
    role: ROLE_ADMIN                                     # who may see the pages
    base_template: base.html.twig                        # needs a "title" and a "body" block
    deploy_log: '%kernel.project_dir%/var/log/deploy.log' # where bin/deploy writes its log
```

Import the routes with the prefix you want:

```yaml
_kuku_sfsys_admin:
  resource: '@KuKuSFSystemToolsBundle/config/routes/admin.yaml'
  prefix: /system
```

This gives `/system/about` (route `kuku_sf_system_about`) and `/system/logview` (route `kuku_sf_system_logview`).
The pages use Bootstrap 5.3 markup and work in light and dark mode; the deploy log only uses links, no JavaScript.
It takes the query parameters `run` (the run's position in the log, oldest first) and `month` (`Y-m`).

### Deploy log format

The log is read as runs: a run starts with a line beginning with `>>>===`, followed by a line with the date,
and ends with a line beginning with `<<<===`. This is what the `bin/deploy` script of
[elkuku/symfony-utils](https://github.com/elkuku/symfony-utils) writes. A run without its end line is shown as
"did not finish", and output outside any run is shown on its own.

## Development

```shell
composer install
vendor/bin/phpunit tests
```
