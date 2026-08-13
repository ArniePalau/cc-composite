# CC Composite

CC Composite is a standalone [Forumify plugin](https://docs.forumify.net/guides/plugin)
that creates the 1080 × 530 layered soldier image previously used by Cavallers
del Cel's local MILHQ installation. It targets Forumify's official PERSCOM.io
plugin and stores the generated image in each soldier's **Uniform** field (the
field synchronized to the PERSCOM cover photo).

It does not patch Forumify or PERSCOM source code.

## Features

- 1080 × 530 background, face, uniform, hair and amulet layers.
- Live preview in Forumify account settings and PERSCOM soldier administration.
- Layer restrictions by PERSCOM rank, unit or individual soldier.
- Global fallback and per-unit defaults for new or uncustomized soldiers.
- Award ribbons/medals split into the same six left/right panel categories as
  the legacy generator, including `xN` repeated-award markers.
- Automatic regeneration after appearance, rank, unit or award-record changes.
- Generated files are written to PERSCOM's own asset storage and the Uniform is
  marked dirty for the normal PERSCOM synchronization workflow.
- Bundled legacy library importer with 19 valid layers, legacy defaults,
  explicit soldier selections, layer permissions and 107 award placements.

Rank/unit/record lifecycle regeneration is queued through Forumify's Messenger
bus, so keep the normal Forumify worker process running in production.

## Requirements

- Forumify with `forumify/forumify-perscom-plugin` 2.2.8 or newer compatible 2.x.
- PHP 8.4 or newer.
- PHP Imagick and the DejaVu Sans fonts (the normal Forumify Docker image already
  provides the font path used by the legacy layout; a font fallback is used if
  it is absent).

## Install

Back up the database and `public/storage/perscom` first. From the Forumify app:

```bash
composer config repositories.cc-composite vcs https://github.com/ArniePalau/cc-composite.git
composer require arniepalau/cc-composite:dev-main
php bin/console forumify:plugins:refresh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console assets:install public
php bin/console cache:clear
```

For the production Docker layout used by Forumify, prefix the PHP commands with
`docker exec forumify` and run Composer in the same container, for example:

```bash
docker exec forumify composer require arniepalau/cc-composite:dev-main
docker exec forumify php bin/console forumify:plugins:refresh
docker exec forumify php bin/console doctrine:migrations:migrate --no-interaction
docker exec forumify php bin/console assets:install public
docker exec forumify php bin/console cache:clear
```

Enable **CC Composite** in the Forumify plugin administration page and grant:

- `cc_composite.admin.view` / `cc_composite.admin.manage` to administrators.
- `cc_composite.frontend.customize` to members who may customize themselves.

## Import the Cavallers del Cel legacy setup

The importer is repeatable: it upserts database records and skips existing
assets unless `--overwrite-assets` is supplied.

```bash
php bin/console cc-composite:import-legacy --overwrite-assets --generate
```

Names that do not exist in the destination PERSCOM data are reported and safely
skipped. This is intentional because the local and online rosters can differ.

Then review these admin pages:

- **CC Composite → Layers**: upload/delete layers and edit rank/unit/user access.
- **CC Composite → Defaults**: configure the global fallback and each unit.
- **CC Composite → Award layout**: choose the panel category for every award.

## Commands

```bash
# Generate one Forumify PERSCOM user by local database ID
php bin/console cc-composite:generate 42

# Generate all users
php bin/console cc-composite:generate --all
```

## Update-safe design

All plugin data is kept in `cc_composite_*` tables. Layer assets are stored in
`public/storage/cc-composite`; generated final images use
`public/storage/perscom/user/uniform/cc-composite`. No vendor or application-core
files are replaced.

## Development

```bash
composer install
vendor/bin/phpunit
```

The plugin follows Forumify's Composer package format (`type: forumify-plugin`),
PSR-4 autoloading, Symfony services, Doctrine mappings and plugin migrations.
