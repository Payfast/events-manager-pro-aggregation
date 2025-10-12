<?php

namespace EM\Payments\Payfast;


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
define('SELECTED_SELECTED', 'selected="selected"');

/**
 * This Gateway is slightly special, because as well as providing public static functions that need to be activated,
 * there are offline payment public static functions that are always there e.g. adding manual payments.
 */
class Gateway_Admin extends \EM\Payments\Gateway_Admin
{
    public static function init()
    {
        parent::init();
    }

    public static function settings_tabs($custom_tabs = array())
    {
        $tabs = array(
                'options' => array(
                        'name'     => sprintf(esc_html__emp('%s Options'), 'Payfast Aggregation'),
                        'callback' => array(static::class, 'mysettings'),
                ),
        );

        return parent::settings_tabs($tabs);
    }

    public static function mysettings()
    {
        global $EM_options;
        ?>
        <table class="form-table">
            <tbody>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Redirect Message', 'em-pro') ?></th>
                <td>
                    <input type="text" name="payfast_booking_feedback" value="<?php
                    esc_attr_e(get_option('em_' . static::$gateway . BOOKING_FEEDBACK_THANKS)); ?>"
                           style='width: 40em;'/><br/>
                    <em>
                        <?php
                        _e('The message that is shown before a user is redirected to Payfast.', 'em-pro'); ?>
                    </em>
                </td>
            </tr>
            </tbody>
        </table>

        <table class="form-table">
            <caption><?php
                echo sprintf(__('%s Options', 'em-pro'), 'Payfast Aggregation'); ?></caption>
            <tbody>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Merchant ID', 'em-pro') ?></th>
                <td>
                    <input type="text" name="merchant_id" value="<?php
                    esc_attr_e(get_option('em_' . static::$gateway . MERCHANT_ID)); ?>"/>
                    <br/>
                </td>
            </tr>
            <tbody>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Merchant Key', 'em-pro') ?></th>
                <td>
                    <input type="text" name="merchant_key" value="<?php
                    esc_attr_e(get_option('em_' . static::$gateway . MERCHANT_KEY)); ?>"/>
                    <br/>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Passphrase', 'em-pro') ?></th>
                <td><input type="text" name="passphrase" value="<?php
                    esc_attr_e(get_option('em_' . static::$gateway . PASSPHRASE)); ?>"/>
                    <br/>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Mode', 'em-pro') ?></th>
                <td>
                    <select name="payfast_status">
                        <option value="live" <?php
                        if (get_option('em_' . static::$gateway . STATUS) == 'live') {
                            echo SELECTED_SELECTED;
                        } ?>><?php
                            _e('Live', 'em-pro') ?></option>
                        <option value="test" <?php
                        if (get_option('em_' . static::$gateway . STATUS) == 'test') {
                            echo SELECTED_SELECTED;
                        } ?>><?php
                            _e('Test Mode (Sandbox)', 'em-pro') ?></option>
                    </select>
                    <br/>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Debug', 'em-pro') ?></th>
                <td>
                    <select name="payfast_debug">
                        <option value="true" <?php
                        if (get_option('em_' . static::$gateway . "_debug") == 'true') {
                            echo SELECTED_SELECTED;
                        } ?>><?php
                            _e('On', 'em-pro') ?></option>
                        <option value="false" <?php
                        if (get_option('em_' . static::$gateway . "_debug") == 'false') {
                            echo SELECTED_SELECTED;
                        } ?>><?php
                            _e('Off', 'em-pro') ?></option>
                    </select>
                    <br/>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Return URL', 'em-pro') ?></th>
                <td>
                    <input type="text" name="payfast_return" value="<?php
                    esc_attr_e(get_option('em_' . static::$gateway . RETURN_API)); ?>" style='width: 40em;'/><br/>
                    <em><?php
                        _e('The URL of the page the user is returned to after payment.', 'em-pro'); ?></em>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <th scope="row"><?php
                    _e('Cancel URL', 'em-pro') ?></th>
                <td>
                    <input type="text" name="payfast_cancel_return" value="<?php
                    esc_attr_e(get_option('em_' . static::$gateway . CANCEL_RETURN)); ?>" style='width: 40em;'/><br/>
                    <em><?php
                        _e('If a user cancels, they will be redirected to this page.', 'em-pro'); ?></em>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    /*
     * Run when saving Payfast settings
     */
    public static function update($options = array())
    {
        $gateway_prefix = static::$gateway;

        $gateway_options = array(
                $gateway_prefix . MERCHANT_ID                => isset($_REQUEST['merchant_id']) ? sanitize_text_field(
                        $_REQUEST['merchant_id']
                ) : '',
                $gateway_prefix . MERCHANT_KEY               => isset($_REQUEST['merchant_key']) ? sanitize_text_field(
                        $_REQUEST['merchant_key']
                ) : '',
                $gateway_prefix . PASSPHRASE                 => isset($_REQUEST['passphrase']) ? sanitize_text_field(
                        $_REQUEST['passphrase']
                ) : '',
                $gateway_prefix . "_currency"                => isset($_REQUEST['currency']) ? sanitize_text_field(
                        $_REQUEST['currency']
                ) : '',
                $gateway_prefix . STATUS                     => isset($_REQUEST[$gateway_prefix . '_status']) ? sanitize_text_field(
                        $_REQUEST[$gateway_prefix . '_status']
                ) : '',
                $gateway_prefix . "_debug"                   => isset($_REQUEST[$gateway_prefix . '_debug'])
                        ? filter_var($_REQUEST[$gateway_prefix . '_debug'], FILTER_SANITIZE_NUMBER_INT)
                        : '',
                $gateway_prefix . "_manual_approval"         => isset($_REQUEST[$gateway_prefix . MANUAL_APPROVAL])
                        ? filter_var($_REQUEST[$gateway_prefix . MANUAL_APPROVAL], FILTER_SANITIZE_NUMBER_INT)
                        : '',
                $gateway_prefix . BOOKING_FEEDBACK           => isset($_REQUEST[$gateway_prefix . '_booking_feedback']) ? wp_kses_data(
                        $_REQUEST[$gateway_prefix . '_booking_feedback']
                ) : '',
                $gateway_prefix . "_booking_feedback_free"   => isset($_REQUEST[$gateway_prefix . '_booking_feedback_free']) ? wp_kses_data(
                        $_REQUEST[$gateway_prefix . '_booking_feedback_free']
                ) : '',
                $gateway_prefix . "_booking_feedback_thanks" => isset($_REQUEST[$gateway_prefix . BOOKING_FEEDBACK_THANKS]) ? wp_kses_data(
                        $_REQUEST[$gateway_prefix . BOOKING_FEEDBACK_THANKS]
                ) : '',
                $gateway_prefix . "_booking_timeout"         => isset($_REQUEST[$gateway_prefix . BOOKING_TIMEOUT])
                        ? filter_var($_REQUEST[$gateway_prefix . BOOKING_TIMEOUT], FILTER_SANITIZE_NUMBER_INT)
                        : '',
                $gateway_prefix . RETURN_API                 => isset($_REQUEST[$gateway_prefix . '_return']) ? esc_url_raw(
                        $_REQUEST[$gateway_prefix . '_return']
                ) : '',
                $gateway_prefix . CANCEL_RETURN              => isset($_REQUEST[$gateway_prefix . '_cancel_return']) ? esc_url_raw(
                        $_REQUEST[$gateway_prefix . '_cancel_return']
                ) : '',
        );

        foreach ($gateway_options as $key => $option) {
            update_option('em_' . $key, stripslashes($option));
        }

        return parent::update($gateway_options);
    }

}

?>
