<?php

namespace EM\Payments\Payfast;

use EM;
use EM_Booking;
use EM_Bookings;
use EM_Multiple_Booking;
use EM_Multiple_Bookings;
use EM_Object;
use EM_Pro;
use EMP_Logs;
use Payfast\PayfastCommon\Aggregator\Request\PaymentRequest;
use Payfast\Classes\PayfastTransaction;

require_once __DIR__ . '/vendor/autoload.php';

// Global constants
define('PF_SOFTWARE_NAME', 'Events Manager');
define('PF_MODULE_NAME', 'PayFast Aggregation-Events Manager');
define('PF_MODULE_VER', '1.2.0');
define('PF_SOFTWARE_VER', '3.6.2');
define('PF_DEBUG', get_option("em_payfast_debug") == 'true' ? true : false);

define('BOOKING_TIMEOUT', '_booking_timeout');
define('MERCHANT_ID', '_merchant_id');
define('MERCHANT_KEY', '_merchant_key');
define('STATUS', '_status');
define('PASSPHRASE', '_passphrase');
define('RETURN_API', '_return');
define('CANCEL_RETURN', '_cancel_return');
define('BOOKING_FEEDBACK_THANKS', '_booking_feedback_thanks');
define('MANUAL_APPROVAL', '_manual_approval');
define('BOOKING_FEEDBACK', '_booking_feedback');

/**
 * This class is a parent class which gateways should extend.
 * There are various variables and functions that are automatically taken care of by
 * EM_Gateway, which will reduce redundant code and unecessary errors across all gateways.
 * You can override any function you want on your gateway,
 * but it's advised you read through before doing so.
 *
 */
class Gateway extends \EM\Payments\Gateway
{

    public static $gateway = 'payfast';
    public static $title = 'Payfast Aggregation';
    public static $status = 4;
    public static $button_enabled = true;
    public static $count_pending_spaces = true;
    public static $can_manually_approve = false;
    public static $supports_multiple_bookings = true;
    public static $status_txt = 'Awaiting Payfast Aggregation Payment';

    /**
     * Sets up gateway and registers actions/filters
     */
    public static function init()
    {
        // Booking Interception
        if (static::is_active() && absint(get_option('em_' . static::$gateway . BOOKING_TIMEOUT)) > 0) {
            static::$count_pending_spaces = true;
        }

        parent::init();
        static::$status_txt = __('Awaiting payment via Payfast', 'em-pro');
        if (static::is_active()) {
            add_action('em_gateway_js', array(static::class, 'em_gateway_js'));
            // Gateway-Specific
            //say thanks on my_bookings page
            add_action('em_template_my_bookings_header', array(static::class, 'say_thanks'));
            add_filter('em_bookings_table_booking_actions_4', array(static::class, 'bookings_table_actions'), 1, 2);
            add_filter('em_my_bookings_booking_actions', array(static::class, 'em_my_bookings_booking_actions'), 1, 2);

            add_action('em_handle_payment_return_' . static::$gateway, array(static::class, 'handle_payment_return'));
            // Set up cron
            $timestamp = wp_next_scheduled('emp_payfast_cron');
            if
            (absint(get_option('em_payfast_booking_timeout')) > 0 && !$timestamp) {
                wp_schedule_event(time(), 'em_minute', 'emp_payfast_cron');
            } elseif (!$timestamp) {
                wp_unschedule_event($timestamp, 'emp_payfast_cron');
            }
        } else {
            // Unschedule the cron
            wp_clear_scheduled_hook('emp_payfast_cron');
        }
    }

    /**
     * Intercepts return data after a booking has been made and adds vars, modifies feedback message.
     *
     * @param array $return
     * @param EM_Booking $EM_Booking
     *
     * @return array
     */
    public static function booking_form_feedback($return, $EM_Booking = false)
    {
        // Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
        if (is_object($EM_Booking) && static::uses_gateway($EM_Booking)) {
            if (!empty($return['result']) && $EM_Booking->get_price(
                    ) > 0 && $EM_Booking->booking_status == static::$status) {
                $return['message'] = get_option('em_payfast_booking_feedback');
                $payfast_url       = static::get_payfast_url();
                $payfast_vars      = static::get_payfast_vars($EM_Booking);
                $payfast_return    = array('payfast_url' => $payfast_url, 'payfast_vars' => $payfast_vars);
                $return            = array_merge($return, $payfast_return);
            } else {
                // Returning a free message
                $return['message'] = get_option('em_payfast_booking_feedback_free');
            }
        }

        return $return;
    }

    /**
     * Adds the Payfast booking button,
     * given the request should have been successful if the booking form feedback msg was called.     *
     *
     * @param string $feedback
     *
     * @return string
     */
    public static function booking_form_feedback_fallback($feedback)
    {
        global $EM_Booking;
        if (is_object($EM_Booking)) {
            $feedback .= "<br />" . __(
                            'To finalize your booking, please click the following button to proceed to Payfast.',
                            'em-pro'
                    ) . static::em_my_bookings_booking_actions('', $EM_Booking);
        }

        return $feedback;
    }

    /**
     * Triggered by the em_booking_add_yourgateway action, hooked in EM_Gateway.
     * Overrides EM_Gateway to account for non-ajax bookings (i.e. broken JS on site).
     *
     * @param EM_Event $EM_Event
     * @param EM_Booking $EM_Booking
     * @param boolean $post_validation
     */
    public static function booking_add($EM_Event, $EM_Booking, $post_validation = false)
    {
        parent::booking_add($EM_Event, $EM_Booking, $post_validation);
        if (!defined(
                'DOING_AJAX'
        )) { //we aren't doing ajax here, so we should provide a way to edit the $EM_Notices ojbect.
            add_action('option_dbem_booking_feedback', array(static::class, 'booking_form_feedback_fallback'));
        }
    }

    /**
     * Instead of a simple status string, a resume payment button
     * is added to the status message so user can resume booking from their my-bookings page.
     *
     * @param string $message
     * @param EM_Booking $EM_Booking
     *
     * @return string
     */
    public static function em_my_bookings_booking_actions($message, $EM_Booking)
    {
        global $wpdb;
        if (static::uses_gateway($EM_Booking) && $EM_Booking->booking_status == static::$status) {
            // First make sure there's no pending payments
            $pending_payments = $wpdb->get_var(
                    'SELECT COUNT(*) FROM '
                    . EM_TRANSACTIONS_TABLE
                    . " WHERE booking_id='{$EM_Booking->booking_id}' AND transaction_gateway='"
                    . static::$gateway . "' AND transaction_status='Pending'"
            );
            if ($pending_payments == 0) {
                //user owes money!
                $payfast_vars = static::get_payfast_vars($EM_Booking);
                $form         = '<form action="' . static::get_payfast_url() . '" method="post">';
                foreach ($payfast_vars as $key => $value) {
                    $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
                }

                $message = 'Awaiting Confirmation';
            }
        }

        return $message;
    }

    // Outputs extra custom content
    public static function booking_form()
    {
        echo get_option('em_' . static::$gateway . '_form');
    }

    // Outputs some JavaScript during the em_gateway_js action, which is run inside a script html tag,
    // located in gateways/gateway.payfast.js
    public static function em_gateway_js()
    {
        include_once 'gateway.payfast.js';
    }

    // Adds relevant actions to booking shown in the bookings table
    public static function bookings_table_actions($actions, $EM_Booking)
    {
        return array(
                'approve' => '<a class="em-bookings-approve em-bookings-approve-offline" href="' . em_add_get_params(
                                $_SERVER['REQUEST_URI'],
                                array('action' => 'bookings_approve', 'booking_id' => $EM_Booking->booking_id)
                        ) . '">' . esc_html__emp('Approve', 'dbem') . '</a>',
                'delete'  => '<span class="trash"><a class="em-bookings-delete" href="' . em_add_get_params(
                                $_SERVER['REQUEST_URI'],
                                array('action' => 'bookings_delete', 'booking_id' => $EM_Booking->booking_id)
                        ) . '">' . esc_html__emp('Delete', 'dbem') . '</a></span>',
                'edit'    => '<a class="em-bookings-edit" href="' . em_add_get_params(
                                $EM_Booking->get_event()->get_bookings_url(),
                                array('booking_id' => $EM_Booking->booking_id, 'em_ajax' => null, 'em_obj' => null)
                        ) . '">' . esc_html__emp('Edit/View', 'dbem') . '</a>',
        );
    }

    public static function get_payfast_vars($EM_Booking)
    {
        $payfast_vars = PayfastTransaction::prepare_payfast_vars($EM_Booking);

        return apply_filters('em_gateway_payfast_get_payfast_vars', $payfast_vars, $EM_Booking, static::class);
    }

    public static function get_payfast_url()
    {
        return (get_option(
                        'em_' . static::$gateway . STATUS
                ) == 'test') ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';
    }

    public static function say_thanks()
    {
        if (!empty($_REQUEST['thanks'])) {
            echo "<div class='em-booking-message em-booking-message-success'>" . get_option(
                            'em_' . static::$gateway . BOOKING_FEEDBACK_THANKS
                    ) . '</div>';
        }
    }

    public static function handle_payment_return()
    {
        if (empty($_POST['payment_status']) || empty($_POST['pf_payment_id'])) {
            return false;
        }

        $paymentRequest = new PaymentRequest(PF_DEBUG);

        PayfastTransaction::process_itn_response($paymentRequest);
    }

    public static function payment_return_local_ca_curl($handle)
    {
        curl_setopt($handle, CURLOPT_CAINFO, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'gateway.payfast.pem');
    }

    /*
     * --------------------------------------------------
     * Booking UI - modifications to booking pages and tables containing offline bookings
     * --------------------------------------------------
     */

    /**
     * Adds a payment form which can be used to submit full or partial offline payments for a booking.
     */
    public static function add_payment_form()
    {
        ?>
        <div id="em-gateway-payment" class="stuffbox">
            <h3>
                <?php
                _e('Add Payfast Aggregation Payment', 'em-pro'); ?>
            </h3>
            <div class="inside">
                <div>
                    <form method="post" action="" style="padding:5px;">
                        <table class="form-table">
                            <tbody>
                            <tr style="vertical-align: top">
                                <th scope="row"><?php
                                    _e('Amount', 'em-pro') ?></th>
                                <td><input type="text" name="transaction_total_amount" value="<?php
                                    if (!empty($_REQUEST['transaction_total_amount'])) {
                                        echo esc_attr(sanitize_text_field($_REQUEST['transaction_total_amount']));
                                    } ?>"/>
                                    <br/>
                                    <em><?php
                                        _e(
                                                'Please enter a valid payment amount (e.g. 10.00). '
                                                . 'Use negative numbers to credit a booking.',
                                                'em-pro'
                                        ); ?></em>
                                </td>
                            </tr>
                            <tr style="vertical-align: top">
                                <th scope="row"><?php
                                    _e('Comments', 'em-pro') ?></th>
                                <td>
                                    <textarea name="transaction_note"><?php
                                        if (!empty($_REQUEST['transaction_note'])) {
                                            echo esc_attr(sanitize_text_field($_REQUEST['transaction_note']));
                                        } ?></textarea>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <input type="hidden" name="action" value="gateway_add_payment"/>
                        <input type="hidden" name="_wpnonce" value="<?php
                        echo wp_create_nonce('gateway_add_payment'); ?>"/>
                        <input type="hidden" name="redirect_to" value="<?php
                        if (!empty($_REQUEST['redirect_to'])) {
                            $redirect_to = esc_url_raw($_REQUEST['redirect_to'], array('http', 'https'));
                            echo esc_attr($redirect_to);
                        } else {
                            $referer = em_wp_get_referer();
                            echo esc_attr(esc_url($referer));
                        }
                        ?>"/>
                        <input type="submit" class="<?php
                        if (is_admin()) {
                            echo 'button-primary';
                        } ?>" value="<?php
                        _e('Add Payfast Payment', 'em-pro'); ?>"/>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /*
     * --------------------------------------------------
     * Settings pages and functions
     * --------------------------------------------------
     */

    /**
     * Checks an EM_Booking object and returns whether or not this gateway is/was used in the booking.
     *
     * @param EM_Booking $EM_Booking
     *
     * @return boolean
     */
    public static function uses_gateway($EM_Booking)
    {
        //for all intents and purposes,
        // if there's no gateway assigned but this booking status matches, we assume it's offline
        return parent::uses_gateway(
                        $EM_Booking
                ) || (empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_status == static::$status);
    }

    /**
     * @param bool $pfError
     * @param bool $pfDone
     *
     * @return void
     */
    public static function notifyPF(bool $pfError, bool $pfDone): void
    {
        if (!$pfError && !$pfDone) {
            header('HTTP/1.0 200 OK');
            flush();
        }
    }

}

Gateway::init();
?>
