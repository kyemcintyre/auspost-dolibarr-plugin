# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Dolibarr ERP/CRM module (external plugin, module name `auspost`) that integrates the Australia Post PAC (Postage Assessment Calculator) API. It lets users fetch live domestic/international shipping rates and insert them as priced lines on Proposals, Sales Orders, and Shipments. Target: Dolibarr 21.0.2 (compatible 16.0–21.x), PHP 8.1–8.3.

The repo *is* the module — it gets deployed as `htdocs/custom/auspost/` inside a Dolibarr install, or packaged as a zip for the module manager. There is no separate "app" to run standalone; PHP files that aren't tests assume a Dolibarr runtime (`$db`, `$conf`, `$user`, `$langs`, `$mysoc` globals, `main.inc.php` bootstrap) unless noted otherwise.

## Commands

```bash
composer install                 # install dev deps (PHPUnit)
composer test                    # run PHPUnit suite (= vendor/bin/phpunit)
vendor/bin/phpunit --testdox     # same, with readable output
vendor/bin/phpunit --filter testMethodName tests/AusPostApiTest.php   # single test
composer run package             # build dist/module_auspost-<version>.zip
php scripts/build_package.php 1.0.0   # package with an explicit version
php -l path/to/file.php          # syntax-check a single file (what CI does for every .php file)
```

There is no separate lint/format command beyond `php -l` syntax checking (run over every non-vendor `.php` file in CI).

## Architecture

**Dolibarr module wiring** ([core/modules/modAuspost.class.php](core/modules/modAuspost.class.php)) — the module descriptor Dolibarr loads to know the module's name, version, constants (default parcel dims, handling fee type/amount, GST rate, API base URL), hook registrations, permissions (`auspost->read`, `auspost->setup`), and menu entry. It also defines a `DolibarrModules` stub class so the file (and anything requiring it) can be parsed/tested outside a real Dolibarr environment. `init()` additionally seeds two custom shipment modes (`AUSPOST_REG`, `AUSPOST_EXP`) into `llx_c_shipment_mode`. **The version string here and in `composer.json` are the semantic-release-managed source of truth** — see Release process below; never hand-edit them out of sync with each other.

**Hook injection** ([class/actions_auspost.class.php](class/actions_auspost.class.php)) — `ActionsAuspost::addMoreActionsButtons()` is Dolibarr's `addMoreActionsButtons` hook, wired for the `propalcard`, `ordercard`, `shipmentcard` contexts (see `module_parts['hooks']` in the descriptor). This is where the "Calculate AusPost Shipping" button gets printed onto a document's action bar. It only renders for draft/open documents when the user has the `read` permission, and it pre-computes the destination (from the thirdparty) and total product weight (converting Dolibarr's unit-scale weights to kg via `auspost.lib.php`) as `data-*` attributes for the JS-driven modal to consume.

**API client** ([class/auspostapi.class.php](class/auspostapi.class.php)) — `AusPostApi` wraps the AusPost PAC REST API over cURL (domestic/international service lookup, domestic cost calculation, postcode search, country list, connection test). All public methods funnel through the single `request()` method, which centralizes error/timeout/HTTP-status handling and JSON decoding. It supports `setMockHandler()` — a callable that intercepts requests in place of cURL — which is how tests exercise it without hitting the network or Dolibarr globals.

**Pure helpers** ([lib/auspost.lib.php](lib/auspost.lib.php)) — framework-light functions: reading config globals (`auspost_get_api_key`, `auspost_get_api_base_url`, `auspost_get_sender_postcode`, each falling back gracefully when `getDolGlobalString` isn't defined), unit conversion (`auspost_convert_weight_to_kg`, `auspost_convert_dim_to_cm` — Dolibarr's unit-scale encoding, e.g. `-3` = grams, `98`/`99` = imperial), and the shipping math (`auspost_calculate_markup`, `auspost_calculate_cubic_weight` — AusPost's `L*W*H/4000` volumetric formula, `auspost_get_billable_weight` — max of actual vs cubic). These are the easiest functions to unit test in isolation and where most business-logic bugs should be fixed.

**AJAX endpoint** ([ajax/calculate.php](ajax/calculate.php)) — single entry point handling three `action` values via `GETPOST`: `test_connection` (used by the admin setup page), `get_rates` (fetches + markup/tax-adjusts rates for a given package/destination), and `apply_to_document` (writes an actual line onto a Propal/Commande, or updates an Expedition's shipping method). Each document type check re-verifies the relevant Dolibarr permission (`propal->creer`, `commande->creer`) before mutating. Note this file sets `NOCSRFCHECK` because it is a same-origin AJAX endpoint invoked with a hook-issued token (`newToken()`) checked in JS — see `js/auspost.js` and the recent CSRF fix commit for how the token is threaded through.

**Front-end**: [js/auspost.js](js/auspost.js) drives the modal calculator UI injected via the hook button's `data-*` attributes, calling into `ajax/calculate.php`. [calculator.php](calculator.php) is the standalone "Commerce > AusPost Calculator" page (menu entry in the module descriptor) for quoting without an attached document. [admin/setup.php](admin/setup.php) is the module configuration page (API key, defaults, markup/tax settings, Test Connection button) and [admin/about.php](admin/about.php) is the About tab; both use `auspost_admin_prepare_head()` from the lib for shared tab navigation.

**Language files**: [langs/en_AU/auspost.lang](langs/en_AU/auspost.lang) and [langs/en_US/auspost.lang](langs/en_US/auspost.lang) hold the `$langs->trans("Key")` strings referenced throughout — when adding a user-facing string, add the key to both.

## Testing

PHPUnit tests ([tests/AusPostApiTest.php](tests/AusPostApiTest.php), [tests/AusPostLibTest.php](tests/AusPostLibTest.php)) run outside Dolibarr entirely. [tests/bootstrap.php](tests/bootstrap.php) stubs the handful of Dolibarr globals/functions the library code touches (`getDolGlobalString`, `getDolGlobalInt`, `dol_syslog`, `price`, `price2num`, a fake `$conf->global`). `AusPostApiTest` uses `AusPostApi::setMockHandler()` to fake HTTP responses rather than hitting the real AusPost API. When adding code that calls a new Dolibarr global function, add a stub for it in `bootstrap.php` rather than requiring a real Dolibarr checkout to test against.

## Release process (do not hand-roll version bumps)

Versioning is fully automated by semantic-release on push to `main`/`master` (see [.releaserc.json](.releaserc.json) and [.github/workflows/build-and-test.yml](.github/workflows/build-and-test.yml)):
- Commit messages must follow [Conventional Commits](https://www.conventionalcommits.org/) (`fix:`, `feat:`, `feat!:`/`BREAKING CHANGE:`, etc.) — the type determines the semver bump.
- On release, CI runs `scripts/set_version.php <version>` (updates `$this->version` in `modAuspost.class.php` and `composer.json`) then `scripts/build_package.php <version>` (builds `dist/module_auspost-<version>.zip`), commits `ChangeLog.md` + the version bumps with `[skip ci]`, tags, and publishes a GitHub Release with the zip attached.
- Never manually edit the version fields or `ChangeLog.md` in a feature PR — they're generated. Just write conventional commit messages.
