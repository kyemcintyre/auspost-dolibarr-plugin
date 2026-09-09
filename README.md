# Australia Post Shipping Calculator for Dolibarr 21.0.2

[![Build & Test](https://github.com/kyemcintyre/auspost-dolibarr-plugin/actions/workflows/build-and-test.yml/badge.svg)](https://github.com/kyemcintyre/auspost-dolibarr-plugin/actions/workflows/build-and-test.yml)
[![Dolibarr Version](https://img.shields.io/badge/Dolibarr-21.0.2%2B-blue.svg)](https://www.dolibarr.org)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20|%208.2%20|%208.3-8892BF.svg)](https://www.php.net)
[![License: GPL-3.0](https://img.shields.io/badge/License-GPLv3-green.svg)](LICENSE)

An enterprise-grade **Australia Post Shipping Rate Calculator** module designed specifically for **Dolibarr ERP/CRM 21.0.2** (and compatible with v16.0 - 21.x).

It integrates directly with Australia Post's official **Postage Assessment Calculator (PAC) API** to fetch live domestic (Parcel Post, Express Post, etc.) and international shipping estimates, calculate volumetric/cubic weights, and insert shipping costs into Proposals and Sales Orders with a single click.

---

## ✨ Features

- 🔑 **API Key Configuration**: Accepts and validates your Australia Post PAC API Key directly in Dolibarr module setup with a live **Test API Connection** diagnostic.
- 📦 **Automated Weight & Dimensions**: Automatically reads destination address and sums total product weights from Proposals, Orders, and Shipments.
- 📐 **Cubic Weight Intelligence**: Automatically calculates billable weight using Australia Post volumetric guidelines (`L x W x H / 4000`).
- ⚡ **1-Click Document Insertion**: Directly adds selected Australia Post services (Parcel Post, Express Post) as line items to Commercial Proposals and Sales Orders with tax (GST) and handling fee rules.
- 💰 **Flexible Markup & Handling Fees**: Apply fixed dollar markups (e.g. `+$3.00`) or percentage handling fees (e.g. `+10%`) to AusPost estimates.
- 🧮 **Standalone Rate Calculator**: Dedicated tool under the **Commerce** menu to quote customers over the phone without creating a document.
- 🌏 **Domestic & International Support**: Quotes all Australian domestic postal codes and overseas destinations supported by AusPost.
- 🚀 **CI/CD & Automated Testing**: GitHub Actions matrix workflow (PHP 8.1, 8.2, 8.3) with PHPUnit test coverage and automated `.zip` release packaging.

---

## 🛠️ Installation

### Option 1: Direct Deploy (Recommended)
1. Download the latest `module_auspost-X.Y.Z.zip` from the [Releases](../../releases) tab or GitHub Actions artifact.
2. In Dolibarr, log in as an administrator.
3. Navigate to **Home > Setup > Modules/Applications > Deploy an external module**.
4. Upload the zip file and enable the **Australia Post** module.

### Option 2: Git Clone into `htdocs/custom`
Clone or copy this repository directly into your Dolibarr `custom` directory as `auspost`:

```bash
cd /path/to/dolibarr/htdocs/custom
git clone https://github.com/kyemcintyre/auspost-dolibarr-plugin.git auspost
```

---

## ⚙️ Configuration

1. In Dolibarr, go to **Home > Setup > Modules/Applications**.
2. Search for **Australia Post** and toggle the switch to **ON**.
3. Click the gear/cog icon to open the module setup page (`auspost/admin/setup.php`).
4. Enter your configuration:
   - **Australia Post API Key**: Obtain a free API key from the [Australia Post Developer Centre (PAC API)](https://developers.auspost.com.au/apis/pac/).
   - **Default Sender Postcode**: Your dispatch warehouse or company postcode (defaults to company postcode).
   - **Package Defaults**: Fallback length, width, height, and weight if products do not define them.
   - **Handling Fee / Markup**: Optional markup to add to Australia Post base rates.
   - **Tax / GST Rate**: Default tax rate for shipping lines (default: 10.0% GST).
5. Click **Test Connection** to verify your API credentials.
6. Click **Save**.

---

## 📋 How to Use

### 1. From Commercial Proposals & Sales Orders
1. Open any draft or open **Proposal** or **Customer Order**.
2. Click the **"Calculate AusPost Shipping"** button in the actions bar.
3. The popup dialog automatically pre-fills the recipient's destination postcode, country, and total cart weight.
4. Click **"Recalculate Rates"** (or view instant rates).
5. Select **Parcel Post** or **Express Post** and click **"Apply to Proposal"** / **"Apply to Order"**.
6. The shipping line will be added with proper pricing and GST!

### 2. From the Standalone Calculator
Navigate to **Commerce > Australia Post Calculator** in Dolibarr to calculate rates at any time.

---

## 🧪 Development & Testing

This project includes a comprehensive test suite using PHPUnit.

### Install Dependencies
```bash
composer install
```

### Run Unit Tests
```bash
composer test
# or
vendor/bin/phpunit --testdox
```

### Build Module Package Zip
```bash
composer run package
# or specify an explicit version
php scripts/build_package.php 1.0.0
```
The output zip will be placed in `dist/module_auspost-<version>.zip`.

---

## 🔄 GitHub Actions CI/CD & Automated Semantic Release

The workflow [`.github/workflows/build-and-test.yml`](.github/workflows/build-and-test.yml) executes on every push and pull request:
1. **Automated Unit Testing & Linting**:
   - Tests on PHP 8.1, 8.2, and 8.3 runners.
   - Validates PHP syntax (`php -l`) across all module files.
   - Executes the PHPUnit test suite.
2. **Semantic Release & Version Population**:
   - On pushes to `main` / `master`, triggers **Semantic Release** using [Conventional Commits](https://www.conventionalcommits.org/) (e.g. `fix: ...`, `feat: ...`, `feat!: ...`).
   - Automatically determines the next semver version (e.g., `1.0.1`, `1.1.0`, `2.0.0`).
   - Executes `scripts/set_version.php` to populate the new version into:
     - `core/modules/modAuspost.class.php` (`$this->version`)
     - `composer.json` (`version`)
   - Executes `scripts/build_package.php` to generate the deployable `dist/module_auspost-<version>.zip`.
   - Generates and commits `ChangeLog.md`.
   - Creates a GitHub Release, tags the commit, and attaches the built zip archive as a downloadable release asset.

---

## 📄 License

This project is licensed under the **GNU General Public License v3.0 or later (GPL-3.0-or-later)** - see the [LICENSE](LICENSE) file for details.