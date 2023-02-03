<?php


use core_payment\helper;

global $CFG, $USER, $DB;
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login();

$orderNumber = required_param("orderNumber", PARAM_ALPHANUMEXT);
$orderId = required_param('orderId', PARAM_ALPHANUMEXT);
$path_json = $CFG->dirroot.'/payment/gateway/sber/'.$orderNumber.'_param.json';
$json_data = json_decode(file_get_contents($path_json), true);
unlink($path_json);
$c = new curl();
$postfields = "orderId=".$orderId."&userName=".$json_data['userName']."&password=".$json_data['password'];

$options = array(
    'returntransfer' => true,
    'timeout' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
);

$response = $c->post("https://3dsec.sberbank.ru/payment/rest/getOrderStatusExtended.do", $postfields, $options);

$data = json_decode($response);

if ($data->errorCode == 0) {
    if ($data->orderStatus == 2) {
        $payable = helper::get_payable($json_data['component'], $json_data['paymentarea'], $json_data['itemid']);
        $paymentRecord = new stdClass();
        $paymentRecord->component = $json_data['component'];
        $paymentRecord->paymentarea = $json_data['paymentarea'];
        $paymentRecord->itemid = $json_data['itemid'];
        $paymentRecord->userid = $USER->id;
        $paymentRecord->amount = $DB->get_field('enrol', 'cost', ['enrol' => 'fee', 'id' => $json_data['itemid']]);
        $paymentRecord->currency = $DB->get_field('enrol', 'currency', ['enrol' => 'fee', 'id' => $json_data['itemid']]);
        $paymentRecord->accountid = $payable->get_account_id();
        $paymentRecord->gateway = 'sber';
        $DB->insert_record('payments', $paymentRecord);

        $paymentrecord = new stdClass();
        $paymentrecord->courseid = $json_data['courseid'];
        $paymentrecord->itemid = $json_data['itemid'];
        $paymentrecord->userid = $USER->id;
        $paymentrecord->currency = $DB->get_field('enrol', 'currency', ['enrol' => 'fee', 'id' => $json_data['itemid']]);;
        $payment_id = $DB->get_field('payments', 'id', ['component' => $json_data['component'], 'paymentarea' => $json_data['paymentarea'], 'itemid' => $json_data['itemid']]);
        $paymentrecord->payment_id = $payment_id;
        $paymentrecord->txn_id = $orderId;
        $paymentrecord->timeupdated = time();

        $DB->insert_record('paygw_sber', $paymentrecord);

        helper::deliver_order($json_data['component'], $json_data['paymentarea'], $json_data['itemid'], $payment_id, $USER->id);

        $url = course_get_url($json_data['courseid']);

        redirect($url, get_string('paymentsuccessful', 'paygw_sber'), 0, 'success');

    }
}

redirect(new moodle_url('/'), get_string('paymentcancelled', 'paygw_sber'));



