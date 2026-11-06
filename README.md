# events-manager-pro-aggregation

## Payfast Aggregation module v1.3.0 for Events Manager Pro v3.2.x

A standalone WordPress plugin that adds the Payfast payment gateway to Events Manager Pro.

## Installation

1. Download the plugin ZIP (`em-payfast-gateway.zip`).
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

## Configuration

1. Go to **Events → Payment Gateways**, find **Payfast Aggregation** and click **Activate**.
2. Click **Settings** and fill in your Merchant ID, Merchant Key, Passphrase, Return URL and Cancel URL.
3. Set **Mode** to *Live* for production or *Test Mode (Sandbox)* for testing.
4. Go to **Events → Settings → Bookings → Pricing Options** and select **ZAR - South African Rand** as the currency (added automatically by this plugin — no file editing required).

Please [click here](https://payfast.io/integration/plugins/events-manager-pro/) more information concerning this module.

## Upgrading from the manual installation (v1.2.1 and earlier)

If you previously installed the gateway by copying files via FTP and editing core plugin files,
remove those changes before installing this plugin:

1. **Remove the FTP-copied files.** Delete the following folder from your server:
   ```
   /wp-content/plugins/events-manager-pro/add-ons/gateways/payfast/
   ```
   Also delete these files from the same `gateways/` directory:
   ```
   gateway.payfast.php
   gateway.payfast-admin.php
   gateway.payfast.js
   gateway.payfast.pem
   ```

2. **Revert the `gateways.php` edit.** Open:
   ```
   /wp-content/plugins/events-manager-pro/add-ons/gateways/gateways.php
   ```
   Find and remove the line you added (around line 88):
   ```php
   include('gateway.payfast.php');
   ```

3. **Revert the `em-functions.php` edit.** Open:
   ```
   /wp-content/plugins/events-manager/em-functions.php
   ```
   Find `function em_get_currencies()` and remove the three `ZAR` entries that were added to
   `$currencies->names`, `$currencies->symbols`, and `$currencies->true_symbols`. The plugin
   now injects ZAR automatically — no file editing required.

## Collaboration

Please submit pull requests with any tweaks, features or fixes you would like to share.