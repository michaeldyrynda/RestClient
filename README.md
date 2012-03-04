RestClient
=============

A simple REST API client with support for DELETE, GET, POST and PUSH requests.

Basic usage
-------------

    <?php
    require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'RestClient.php';

    $restclient = new RestClient( 'REST API base url' );

    // Sample GET
    $response = $restclient->execute(
        RestClient::REQUEST_TYPE_GET,
        '/api/path',
        array( 'q' => 'query', )
    );

    print_r( $response );

See `test.php` for a working example.
