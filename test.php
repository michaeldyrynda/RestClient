<?php
/**
 * RestClient test
 *
 * @subpackage REST
 * @copyright  2012 IATSTUTI
 * @author     Michael Dyrynda <michael@iatstuti.net>
 * @package    Utility
 */
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'RestClient.php';

try {
    $restclient = new RestClient( 'http://search.twitter.com' );

    $response = $restclient->execute(
        RestClient::REQUEST_TYPE_GET,
        '/search.json',
        array(),
        array( 'q' => '@foxfooty', )
    );

    $body = json_decode( $response['body'] );
    print '<pre>' . var_export( $body, true ) . '</pre>';
} catch ( Exception $e ) {
    print_r( $e->getMessage() ) . PHP_EOL;
}
