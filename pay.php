<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Redirects to the cardinity checkout for payment
 *
 * @package    paygw_cardinity
 * @copyright  2021 Brain station 23 ltd.
 * @author     Brain station 23 ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login();
global $CFG, $USER, $DB;

$component = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid = required_param('itemid', PARAM_INT);
$courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => $itemid]);
$config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'sber');
$surcharge = helper::get_gateway_surcharge('sber');
$cost = $DB->get_field('enrol', 'cost', ['enrol' => 'fee', 'id' => $itemid]);
$cost = $cost * 100;
$url = $config->url;
$username = $config->login;
$password = $config->password;

$orderNumber = uniqid();
$param = [
    'courseid' => $courseid,
    'component' => $component,
    'paymentarea' => $paymentarea,
    'itemid' => $itemid,
    'orderNumber' => $orderNumber,
    'userName' => $username,
    'password' => $password
];
file_put_contents($orderNumber.'_param.json', json_encode($param));
$returnurl = $CFG->wwwroot . '/payment/gateway/sber/save.php?orderNumber='.$orderNumber;

$c = new curl();

$postfields = "amount=".$cost."&orderNumber=".$orderNumber."&userName=".$username."&password=".$password."&returnUrl=".$returnurl;


$options = array(
    'returntransfer' => true,
    'timeout' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
);


$response = $c->post($url, $postfields, $options);


if ($json = json_decode($response)) {

    $formUrl = $json->formUrl;

    redirect($formUrl);

}
?>