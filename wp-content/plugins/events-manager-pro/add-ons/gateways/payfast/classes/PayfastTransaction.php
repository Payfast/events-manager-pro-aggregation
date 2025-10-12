<?php

namespace Payfast\Classes;

use EM\Payments\Payfast\Gateway;
use EM_Booking;
use Payfast\PayfastCommon\Aggregator\Request\PaymentRequest;

class PayfastTransaction
{
    const MERCHANT_ID   = '_merchant_id';
    const MERCHANT_KEY  = '_merchant_key';
    const PASSPHRASE    = '_passphrase';
    const RETURN_API    = '_return';
    const CANCEL_RETURN = '_cancel_return';

    const STATUS             = '_status';
    const BOOKING_TIMEOUT    = '_booking_timeout';
    const BOOKING_ID         = '_booking_id';
    const BOOKING_EVENT_ID   = '_booking_event_id';
    const BOOKING_EVENT_NAME = '_booking_event_name';

    public static function prepare_payfast_vars($EM_Booking)
    {
        global $wp_rewrite, $EM_Notices;
        $notify_url                   = Gateway::get_payment_return_url();
        $payfast_vars                 = array();
        $pf_merchant_id               = get_option('em_' . Gateway::$gateway . self::MERCHANT_ID);
        $pf_merchant_key              = get_option('em_' . Gateway::$gateway . self::MERCHANT_KEY);
        $payfast_vars['merchant_id']  = $pf_merchant_id;
        $payfast_vars['merchant_key'] = $pf_merchant_key;
        $passPhrase                   = get_option('em_' . Gateway::$gateway . self::PASSPHRASE);

        if (!empty(get_option('em_' . Gateway::$gateway . self::RETURN_API))) {
            $payfast_vars['return_url'] = get_option('em_' . Gateway::$gateway . self::RETURN_API);
        }

        if (!empty(get_option('em_' . Gateway::$gateway . self::CANCEL_RETURN))) {
            $payfast_vars['cancel_url'] = get_option('em_' . Gateway::$gateway . self::CANCEL_RETURN);
        }

        $payfast_vars['notify_url'] = $notify_url;

        $payfast_vars['name_first'] = $EM_Booking->get_person()->get_name();

        $payfast_vars['m_payment_id'] = $EM_Booking->booking_id;
        $payfast_vars['amount']       = $EM_Booking->get_price();
        $payfast_vars['item_name']    = $EM_Booking->get_event()->event_name;

        $pfOutput = '';
        // Create output string
        foreach ($payfast_vars as $key => $val) {
            if ($val !== '') {
                $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
            }
        }
        // Remove last ampersand
        $pfOutput = substr($pfOutput, 0, -1);
        if ($passPhrase !== null) {
            $pfOutput .= '&passphrase=' . urlencode(trim($passPhrase));
        }
        $payfast_vars['signature'] = md5($pfOutput);

        return $payfast_vars;
    }


    /**
     * @param PaymentRequest $paymentRequest
     *
     * @return void
     */
    public static function process_itn_response(PaymentRequest $paymentRequest)
    {
        $paymentRequest->pflog('Payfast ITN call received');

        $pfError       = false;
        $pfErrMsg      = '';
        $pfDone        = false;
        $pfData        = array();
        $pfParamString = '';
        $moduleInfo    = [
            'pfSoftwareName'       => 'Events Manager',
            'pfSoftwareVer'        => '3.6.2',
            'pfSoftwareModuleName' => 'PayFast-Events Manager',
            'pfModuleVer'          => '1.2.0',
        ];
        $pfHost        = (get_option(
                              'em_' . Gateway::$gateway . STATUS
                          ) == 'test') ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';

        //// Notify Payfast that information has been received
        Gateway::notifyPF($pfError, $pfDone);

        if (!$pfError && !$pfDone) {
            $paymentRequest->pflog('Get posted data');

            // Posted variables from ITN
            $pfData = $paymentRequest->pfGetData();

            $paymentRequest->pflog('Payfast Data: ' . print_r($pfData, true));

            if ($pfData === false) {
                $pfError  = true;
                $pfErrMsg = $paymentRequest::PF_ERR_BAD_ACCESS;
            }
        }

        //// Verify security signature
        list($pfParamString, $pfError, $pfErrMsg) = self::verifySignature($pfError, $pfData, $pfParamString, $pfErrMsg);

        if (!$pfError) {
            $paymentRequest->pflog('Verify data received');

            $pfValid = $paymentRequest->pfValidData($moduleInfo, $pfHost, $pfParamString);

            if (!$pfValid) {
                $pfError  = true;
                $pfErrMsg = $paymentRequest::PF_ERR_BAD_ACCESS;
            }
        }

        if ($pfError) {
            $paymentRequest->pflog('Error occurred: ' . $pfErrMsg);
        }

        // Handle cases that the system must ignore
        if (!$pfError && !$pfDone) {
            $paymentRequest->pflog('check status and update order');

            $new_status = false;

            // Sanitize and validate inputs
            $amount_raw     = isset($_POST['amount_gross'])
                ? filter_var($_POST['amount_gross'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : null;
            $booking_id_raw = isset($_POST['m_payment_id']) ? sanitize_text_field($_POST['m_payment_id']) : null;
            // Common variables
            $amount     = is_numeric($amount_raw) ? floatval($amount_raw) : 0.00;
            $currency   = 'ZAR';
            $timestamp  = date('Y-m-d H:i:s');
            $booking_id = $booking_id_raw;
            $EM_Booking = $EM_Booking = em_get_booking($booking_id);
            // Booking exists
            // Override the booking ourselves:
            $EM_Booking->manage_override = true;
            $user_id                     = $EM_Booking->person_id;
        }

        // Process Payfast response
        match (sanitize_text_field($_POST['payment_status'])) {
            'COMPLETE' => self::paymentComplete($EM_Booking, $amount, $currency, $timestamp),

            'FAILED' => self::paymentFailed($EM_Booking, $amount, $currency, $timestamp),

            'PENDING' => self::paymentPending($EM_Booking, $amount, $currency, $timestamp),

            default => null,
        };
    }

    /**
     * @param bool $pfError
     * @param mixed $pfData
     * @param string $pfParamString
     * @param string $pfErrMsg
     *
     * @return array
     */
    public static function verifySignature(bool $pfError, mixed $pfData, string $pfParamString, string $pfErrMsg): array
    {
        if (!$pfError) {
            $paymentRequest = new PaymentRequest(PF_DEBUG);

            $paymentRequest->pflog('Verify security signature');


            $pf_merchant_id  = get_option('em_' . Gateway::$gateway . MERCHANT_ID);
            $pf_merchant_key = get_option('em_' . Gateway::$gateway . MERCHANT_KEY);
            $passPhrase      = get_option('em_' . Gateway::$gateway . PASSPHRASE);
            $pfPassphrase    = (empty($passPhrase) ||
                                empty($pf_merchant_id) ||
                                empty($pf_merchant_key))
                ? null : $passPhrase;

            // If signature different, log for debugging
            if (!$paymentRequest->pfValidSignature($pfData, $pfParamString, $pfPassphrase)) {
                $pfError  = true;
                $pfErrMsg = $paymentRequest::PF_ERR_INVALID_SIGNATURE;
            }
        }

        return array($pfParamString, $pfError, $pfErrMsg);
    }

    public static function paymentComplete($EM_Booking, mixed $amount, string $currency, string $timestamp): void
    {
        $paymentRequest = new PaymentRequest(PF_DEBUG);

        $paymentRequest->pflog('-Complete');

        $pfPaymentId   = isset($_POST['pf_payment_id']) ? sanitize_text_field($_POST['pf_payment_id']) : '';
        $paymentStatus = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';

        // Case: successful payment
        Gateway::record_transaction(
            $EM_Booking,
            $amount,
            $currency,
            $timestamp,
            $pfPaymentId,
            $paymentStatus,
            ''
        );
        if (isset($_POST['amount_gross'])) {
            $amount_gross = filter_var(
                $_POST['amount_gross'],
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION
            );

            if ($amount_gross >= $EM_Booking->get_price() && (!get_option(
                        'em_' . Gateway::$gateway . MANUAL_APPROVAL,
                        false
                    ) || !get_option('dbem_bookings_approval'))) {
                // Approve and ignore spaces
                $EM_Booking->approve(true, true);
            } else {
                $EM_Booking->set_status(0); //Set back to normal "pending"
            }
        }
        do_action('em_payment_processed', $EM_Booking, Gateway::class);
    }

    public static function paymentFailed($EM_Booking, mixed $amount, string $currency, string $timestamp): void
    {
        $paymentRequest = new PaymentRequest(PF_DEBUG);

        $paymentRequest->pflog('- Failed');
        // Case: denied
        $note          = 'Last transaction failed';
        $pfPaymentId   = isset($_POST['pf_payment_id']) ? sanitize_text_field($_POST['pf_payment_id']) : '';
        $paymentStatus = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
        Gateway::record_transaction(
            $EM_Booking,
            $amount,
            $currency,
            $timestamp,
            $pfPaymentId,
            $paymentStatus,
            $note
        );
        $EM_Booking->cancel();
        do_action('em_payment_denied', $EM_Booking, Gateway::class);
    }

    public static function paymentPending($EM_Booking, mixed $amount, string $currency, string $timestamp): void
    {
        $paymentRequest = new PaymentRequest(PF_DEBUG);

        $paymentRequest->pflog('- Pending');
        // Case: pending
        $note          = 'Last transaction is pending. Reason: ';
        $txnId         = isset($_POST['txn_id']) ? sanitize_text_field($_POST['txn_id']) : '';
        $paymentStatus = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
        Gateway::record_transaction(
            $EM_Booking,
            $amount,
            $currency,
            $timestamp,
            $txnId,
            $paymentStatus,
            $note
        );
        do_action('em_payment_pending', $EM_Booking, Gateway::class);
    }
}
