# Changelog

## [1.3.0](https://github.com/Payfast/events-manager-pro-aggregation/releases/tag/v1.3.0)

### Added

* Standalone WordPress plugin package with automatic Events Manager Pro registration.
* ZAR currency support in Events Manager Pro booking settings.
* Migration/upgrade guide for manual file-copy installations.

### Changed

* Updated PHP compatibility up to 8.3 and Events Manager Pro up to 3.9.4.
* Restructured files into a standalone plugin scaffold to prevent updates from overwriting gateway files.
* Standardized public branding to "Payfast".

### Fixed

* Fixed empty AJAX response and post-booking redirect failures on checkout for Events Manager Pro 3.9.4.
* Fixed gateway initialization sequence to prevent silent crashes during submission.
* Fixed saving of gateway administrative settings.
* Hardened ITN notification handling with prepared statements and input validation.
* Added fallbacks for cURL certificate verification and missing PHP cURL extension.

## [1.2.1](https://github.com/Payfast/events-manager-pro-aggregation/releases/tag/v1.2.1)

### Fixed

- Cancelling a transaction on the gateway's payment page now releases the reserved booking.

## [1.2.0](https://github.com/Payfast/events-manager-pro-aggregation/releases/tag/v1.2.0)

### Added

- Revised configuration branding to Payfast Aggregation.

## [1.1.0](https://github.com/Payfast/events-manager-pro-aggregation/releases/tag/v1.1.0)

### Added

- Added Support for Events Manager Pro v3.5 and Events Manager v6.6.4.4.
- Updated to the latest Payfast Common library 1.4.0.
- Added support for PHP 8.2.
- Bug fixes and Code Quality improvements.

## [1.0.1](https://github.com/Payfast/events-manager-pro-aggregation/releases/tag/v1.0.1)

### Added

- Update for PHP 8.1.
- Bug fixes and improvements.

### Tested

- Test Events Manager v6.4.7.2 and Events Manager Pro v3.2.8.1.

## [1.0.0](https://github.com/Payfast/events-manager-pro-aggregation/releases/tag/v1.0.0)

### Initial Release
