<?php
/**
 * Payfast Gateway Constants
 * Centralized constant definitions to avoid duplication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Software and Module Info
if (!defined('PF_SOFTWARE_NAME')) {
    define('PF_SOFTWARE_NAME', 'Events Manager');
}
if (!defined('PF_MODULE_NAME')) {
    define('PF_MODULE_NAME', 'Payfast Aggregation-Events Manager');
}
if (!defined('PF_MODULE_VER')) {
    define('PF_MODULE_VER', '1.3.0');
}
if (!defined('PF_SOFTWARE_VER')) {
    define('PF_SOFTWARE_VER', '3.9.4');
}
if (!defined('PF_DEBUG')) {
    define('PF_DEBUG', get_option("em_payfast_debug") == 'true' ? true : false);
}

// Gateway Settings Keys
if (!defined('BOOKING_TIMEOUT')) {
    define('BOOKING_TIMEOUT', '_booking_timeout');
}
if (!defined('MERCHANT_ID')) {
    define('MERCHANT_ID', '_merchant_id');
}
if (!defined('MERCHANT_KEY')) {
    define('MERCHANT_KEY', '_merchant_key');
}
if (!defined('STATUS')) {
    define('STATUS', '_status');
}
if (!defined('PASSPHRASE')) {
    define('PASSPHRASE', '_passphrase');
}
if (!defined('RETURN_API')) {
    define('RETURN_API', '_return');
}
if (!defined('CANCEL_RETURN')) {
    define('CANCEL_RETURN', '_cancel_return');
}
if (!defined('BOOKING_FEEDBACK_THANKS')) {
    define('BOOKING_FEEDBACK_THANKS', '_booking_feedback_thanks');
}
if (!defined('MANUAL_APPROVAL')) {
    define('MANUAL_APPROVAL', '_manual_approval');
}
if (!defined('BOOKING_FEEDBACK')) {
    define('BOOKING_FEEDBACK', '_booking_feedback');
}
if (!defined('SELECTED_SELECTED')) {
    define('SELECTED_SELECTED', 'selected="selected"');
}

