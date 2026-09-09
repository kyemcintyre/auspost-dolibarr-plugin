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
