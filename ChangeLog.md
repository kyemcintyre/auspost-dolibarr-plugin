## [1.0.5](https://github.com/kyemcintyre/auspost-dolibarr-plugin/compare/v1.0.4...v1.0.5) (2026-09-10)


### Bug Fixes

* correct white-on-grey contrast on module buttons ([dd0d2f3](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/dd0d2f3d05e17d3d221734caec0be1f9728ff118))
* load module lang file and reject non-draft docs in apply_to_document ([6867b6f](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/6867b6f4de0fa22430d3d3f523a58e93cb77ff4e))

## [1.0.4](https://github.com/kyemcintyre/auspost-dolibarr-plugin/compare/v1.0.3...v1.0.4) (2026-09-10)


### Bug Fixes

* use Form class instead of FormProduct for select_produits ([0c361a1](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/0c361a15a3feb295dac61e8ad51af6c32977bf0d))

## [1.0.3](https://github.com/kyemcintyre/auspost-dolibarr-plugin/compare/v1.0.2...v1.0.3) (2026-09-10)


### Bug Fixes

* correct invalid GETPOST filter blocking module settings save ([7b7336b](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/7b7336bed360943959cc4f490982a72d804c5760))

## [1.0.2](https://github.com/kyemcintyre/auspost-dolibarr-plugin/compare/v1.0.1...v1.0.2) (2026-09-09)


### Bug Fixes

* resolve CSRF 403 token check in AJAX endpoints and dynamic URL resolution ([7c8c212](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/7c8c2121a230599d1d19342847c1124f29a11d1a))

## [1.0.1](https://github.com/kyemcintyre/auspost-dolibarr-plugin/compare/v1.0.0...v1.0.1) (2026-09-09)


### Bug Fixes

* resolve NOREQUIREDB constant error in Dolibarr page loaders ([87061c3](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/87061c3e2a193741aa50fcd93a0235dfe381450e))
* update composer validation to allow version field without failing ([cf28550](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/cf285507abf49e17ceeafe49fa114374298103bd))

# 1.0.0 (2026-09-09)


### Features

* initialize Australia Post integration module for Dolibarr with PAC API support, shipping rate calculators, and CI/CD workflows ([91b1509](https://github.com/kyemcintyre/auspost-dolibarr-plugin/commit/91b1509b001819b93ca941e6de798ce20bd96518))

# ChangeLog

## [1.0.0] - 2026-09-10
### Initial Release
- Compatible with Dolibarr 21.0.2 (and 16.0 - 21.x).
- Direct integration with Australia Post Postage Assessment Calculator (PAC) API.
- Module setup page for Australia Post API key, origin postcode, packaging defaults, markup, and tax rate.
- Interactive "Test Connection" button in module admin.
- Dynamic hook for Commercial Proposals (`propalcard`), Sales Orders (`ordercard`), and Shipments (`shipmentcard`).
- Rate calculation modal dialog with 1-click line item insertion into proposals and orders.
- Volumetric / cubic weight calculation (L * W * H / 4000).
- Standalone Australia Post Shipping Rate Calculator page under Commerce menu.
- Support for Australian domestic services (Parcel Post, Express Post) and international parcels.
- Automated packaging script (`scripts/build_package.php`).
- GitHub Actions CI/CD workflow with PHP matrix testing (PHP 8.1, 8.2, 8.3), syntax validation, PHPUnit tests, and automated release packaging.
