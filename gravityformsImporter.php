<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

error_reporting( E_ALL );

# Includes the autoloader for libraries installed with composer
require __DIR__ . '/vendor/autoload.php';

include( 'settings.php' );
$settings['cookiefile'] = "cookies.tmp";
use Nischayn22\MediaWikiApi;

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
$response = json_decode( MediaWikiApi::httpRequest( $url ) );
$form_fields = array();
foreach($response->response->fields as $field) {
	$form_fields[$field->id] = $field->label;
	if ( is_array( $field->inputs ) ) {
		foreach( $field->inputs as $input ) {
			$form_fields[$input->id] = $input->label;
		}
	}
}
$route = "forms/$form_id/entries";
$string_to_sign = sprintf( '%s:%s:%s:%s', $api_key, $method, $route, $expires );
$sig = calculate_signature( $string_to_sign, $private_key );

$wikiApi = new MediaWikiApi($settings['wiki_api']);
echo "Logging in to the wiki\n";
$wikiApi->logout();
if( $wikiApi->login($settings['wiki_user'], $settings['wiki_pass']) ) {
	echo "Successfully logged in\n";
} else {
	echo "Login failed!\n";
}

$url = $base_url . $route . '?api_key=' . $api_key . '&signature=' . $sig . '&expires=' . $expires;
$response = json_decode( MediaWikiApi::httpRequest( $url ) );
$name_fields = explode( ' ', $settings['page_name'] );
foreach($response->response->entries as $entry) {
	$entry = (array) $entry;
	$field_values = array();
	foreach( $entry as $key => $value ) {
		if (array_key_exists($key, $form_fields)) {
			if ( $settings['upload_links'] && strstr($value, 'http') ) {
				file_put_contents( basename( $value ), file_get_contents( $value ) );
				if ( $wikiApi->upload( basename( $value ), __DIR__ . '/' . basename( $value ) ) ) {
					$value = basename( $value );
				}
				unlink( __DIR__ . '/' . basename( $value ) );
			}
			$field_values[$key] = $value;
		}
	}
	$pageName = array();
	foreach	( $name_fields as $field ) {
		$pageName[] = $entry[$field];
	}
	$pageName = implode( ' ', $pageName );
	$content = "
	{{" . $settings['template_name'] . "
	|" . 
	implode("|\n", array_map(
		function ($v, $k) { return $k . '=' . $v; },
		$field_values,
		array_keys( $field_values )
		)
	) . 
	"}}";
	$wikiApi->editPage( $pageName, $content );
	echo "Copied page $pageName \n";
}
echo "All done. \n";