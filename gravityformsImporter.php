<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

error_reporting( E_STRICT );
date_default_timezone_set('Asia/Kolkata');

include( 'settings.php' );
include 'MediaWiki_Api/MediaWiki_Api_functions.php';

function calculate_signature( $string, $private_key ) {
    $hash = hash_hmac( 'sha1', $string, $private_key, true );
    $sig = rawurlencode( base64_encode( $hash ) );
    return $sig;
}
 
$base_url = $settings['base_url'];
$api_key = $settings['api_key'];
$private_key = $settings['private_key'];
$form_id = $settings['form_id'];

$method  = 'GET';
$expires = strtotime( '+10 mins' );

$route = "forms/$form_id";
$string_to_sign = sprintf( '%s:%s:%s:%s', $api_key, $method, $route, $expires );
$sig = calculate_signature( $string_to_sign, $private_key );

$url = $base_url . $route . '?api_key=' . $api_key . '&signature=' . $sig . '&expires=' . $expires;
$response = json_decode( httpRequest( $url ) );
$form_fields = array();
foreach($response->response->fields as $field) {
	$form_fields[$field->id] = $field->label;
}


$route = "forms/$form_id/entries";
$string_to_sign = sprintf( '%s:%s:%s:%s', $api_key, $method, $route, $expires );
$sig = calculate_signature( $string_to_sign, $private_key );
 
$url = $base_url . $route . '?api_key=' . $api_key . '&signature=' . $sig . '&expires=' . $expires;
$response = json_decode( httpRequest( $url ) );
foreach($response->response->entries as $entry) {
	$field_values = array();
	foreach( $entry as $key => $value ) {
		if (array_key_exists($key, $form_fields)) {
			$field_values[$form_fields[$key]] = $value;
		}
	}
	file_put_contents("test.data", json_encode($field_values));
	var_dump($field_values);
	die();
	
}