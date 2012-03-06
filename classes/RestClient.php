<?php
/**
 * REST API client
 *
 * @subpackage REST
 * @copyright  2012 IATSTUTI
 * @author     Michael Dyrynda <michael@iatstuti.net>
 * @package    Utility
 */


/**
 * REST API Client
 *
 * @package    Utility
 * @subpackage REST
 */
class RestClient {
    /**#@+
     * Class constants
     */
    /**#@+
     * Exceptions
     */

    /**#@+
     * Generic exceptions
     */
    const EXCEPTION_CANNOT_INSTANTIATE_NO_URL   = 'Cannot instantiate RestClient without a URL';
    const EXCEPTION_REST_NO_URL                 = 'REST API URL has not been set';
    const EXCEPTION_RETURN_FORMAT_UNSUPPORTED   = 'The specified return format is unsupported';
    /**#@-*/

    /**#@+
     * cURL exceptions
     */
    const EXCEPTION_CURL_NOT_INITIALISED    = 'cURL instance not initialised';
    /**#@-*/

    /**#@+
     * Request exceptions
     */
    const EXCEPTION_REQUEST_NO_PATH         = 'Cannot process request without a path';
    const EXCEPTION_REQUEST_INVALID_TYPE    = 'Invalid request type specified';
    /**#@-*/
    /**#@-*/

    /**#@+
     * Valid request types
     */
    const REQUEST_TYPE_DELETE   = 'DELETE';
    const REQUEST_TYPE_GET      = 'GET';
    const REQUEST_TYPE_POST     = 'POST';
    const REQUEST_TYPE_PUT      = 'PUT';
    /**#@-*/

    /**#@+
     * Valid return formats
     */
    const RETURN_FORMAT_JSON    = 'json';
    const RETURN_FORMAT_ARRAY   = 'array';
    const RETURN_FORMAT_DEFAULT = self::RETURN_FORMAT_JSON;
    /**#@-*/

    /**#@+
     * RestClient options
     */
    const OPTION_VERBOSE    = 'verbose';
    /**#@-*/
    /**#@-*/

    /**#@+
     * Class variables
     */
    /**#@+
     * Private class variables
     *
     * @access private
     */

    /**
     * REST API url
     *
     * @var string $_url REST API url
     */
    private $_url = '';

    /**
     * cURL resource handle for requests
     *
     * @var resource $_ch cURL resource handle for requests
     */
    private $_ch = null;

    /**
     * User agent to be sent with requests
     *
     * @var string $_userAgent User agent to be sent with requests
     */
    private $_userAgent = '';

    /**
     * REST API response
     *
     * @var array $_response REST API response
     */
    private $_response = array();

    /**
     * Whether or not cURL is initialised
     *
     * @var bool $_initialised Whether or not cURL is initialised
     */
    private $_initialised = false;

    /**
     * Format to return results in
     *
     * @var string $_returnFormat Format to return results in
     */
    private $_returnFormat = '';

    /**
     * Whether or not we should be verbose
     *
     * @var bool $_verbose Whether or not we should be verbose
     */
    private $_verbose = false;
    /**#@-*/

    /**#@+
     * Public class variables
     *
     * @access public
     */
    /**#@-*/

    /**
     * Class constructor
     *
     * @access public
     * @throws RestException if url not supplied
     * @return void
     * @param string  $url          (optional) REST API url to interact with
     * @param string  $returnFormat (optional) Format to return response in
     * @param string  $userAgent    (optional) User agent we identify ourselves as
     * @param array   $options      (optional) Additional options to be set
     */
    public function RestClient( $url = null, $returnFormat = null, $userAgent = null, $options = array()  ) {
        if ( is_null( $url ) ) {
            throw new RestException( self::EXCEPTION_CANNOT_INSTANTIATE_NO_URL );
        }

        // If $returnFormat is not set, use default
        if ( is_null( $returnFormat ) || trim( $returnFormat ) == '' ) {
            $returnFormat = self::RETURN_FORMAT_DEFAULT;
        }

        // If $userAgent is not set, use default
        if ( is_null( $userAgent ) || trim( $userAgent ) == '' ) {
            $userAgent = sprintf( 'RestClient/cURL PHP %s', phpversion() );
        }

        if ( is_array( $options ) && count( $options ) > 0 ) {
            $this->_parseOptions( $options );
        }

        $this->_startcURL();
        $this->_setURL( $url );
        $this->_setUserAgent( $userAgent );
        $this->_setReturnFormat( $returnFormat );
    }


    /**
     * Send HTTP request
     *
     * @access public
     * @throws RESTHTTPException if cURL not initialised, invalid request type
     * @param string  $type   HTTP request type to send
     * @param string  $path   Path to send request to
     * @param array   $params (optional) Parameters to send with request
     * @param array   $header (optional) Any additional headers to be sent with request
     * @param string  $user   (optional) If set, set the HTTP BASIC Authorisation user
     * @param string  $pass   (optional) If set, set the HTTP BASIC Authorisation pass
     * @return array
     */
    public function execute( $type, $path, $params = array(), $header = array(), $user = null, $pass = null ) {
        if ( $this->cURLInitialised() === false ) {
            throw new RestException( self::EXCEPTION_CURL_NOT_INITIALISED );
        }

        if ( $this->_isValidRequestType( $type ) === false ) {
            throw new RestException(
                sprintf( '%s (%s)', self::EXCEPTION_REQUEST_INVALID_TYPE, $type  )
            );
        }

        if ( trim( $path ) == '' ) {
            throw new RestException( self::EXCEPTION_REQUEST_NO_PATH );
        }

        curl_setopt( $this->_ch, CURLOPT_CUSTOMREQUEST, $type );

        switch ( $type ) {
            case self::REQUEST_TYPE_DELETE:
                $this->_initPostFields( $params );
            break;

            case self::REQUEST_TYPE_GET:
                curl_setopt( $this->_ch, CURLOPT_HTTPGET, true );
            break;

            case self::REQUEST_TYPE_POST:
                curl_setopt( $this->_ch, CURLOPT_POST, true );
                $this->_initPostFields( $params );
            break;

            case self::REQUEST_TYPE_PUT:
                $this->_initPostFields( $params );
            break;

            default:
                // We already check the request type is valid, so default case can do nothing
            break;
        }

        $this->_initAuthorisation( $user, $pass );
        $this->_initHeader( $header );
        $this->_initURL( $path, $params );
        $this->_runcURL();

        return $this->_response;
    }


    /**#@+
     * Getter methods
     */

    /**
     * Return whether or not cURL is initialised
     *
     * @access public
     * @return bool
     */
    public function cURLInitialised() {
        return $this->_initialised;
    }


    /**
     * Return whether or not we are in verbose mode
     *
     * @access public
     * @return bool
     */
    public function beVerbose() {
        return $this->_verbose;
    }


    /**
     * Retrieve the current working URL
     *
     * @access public
     * @return string
     */
    public function getURL() {
        return $this->_url;
    }


    /**
     * Retrieve the current user agent
     *
     * @access public
     * @return string
     */
    public function getUserAgent() {
        return $this->_userAgent;
    }


    /**#@-*/


    /**#@+
     * Setter methods
     */

    /**
     * Set the URL we will be interacting with
     *
     * @access private
     * @throws RestException if url is null or empty
     * @return void
     * @param string  $url URL we will be interacting with
     */
    private function _setURL( $url ) {
        if ( is_null( $url ) || trim( $url ) == '' ) {
            throw new RestException( self::EXCEPTION_CANNOT_INSTANTIATE_NO_URL );
        }

        $this->_url = $url;
    }


    /**
     * Set the user agent we identify ourselves as
     *
     * @access private
     * @throws RestException if cURL not initialised
     * @return void
     * @param string  $userAgent User agent to identify ourselves as
     */
    private function _setUserAgent( $userAgent ) {
        if ( $this->cURLInitialised() === false ) {
            throw new RestException( self::EXCEPTION_CURL_NOT_INITIALISED );
        }

        $this->_userAgent = $userAgent;
        curl_setopt( $this->_ch, CURLOPT_USERAGENT, $this->_userAgent );
    }


    /**
     * Set the return format for our requests
     *
     * @access private
     * @throws RestException if return format is invalid
     * @return void
     * @param string  $returnFormat The return format to set
     */
    private function _setReturnFormat( $returnFormat ) {
        if ( $this->_isValidReturnFormat( $returnFormat ) === false ) {
            throw new RestException(
                sprintf( '%s (%s)', self::EXCEPTION_RETURN_FORMAT_UNSUPPORTED, $returnFormat )
            );
        }

        $this->_returnFormat = $returnFormat;
    }


    /**#@-*/


    /**#@+
     * cURL methods
     */

    /**
     * Initiate a cURL instance
     *
     * @access private
     * @return void
     */
    private function _startcURL() {
        $this->_ch = curl_init();
        curl_setopt( $this->_ch, CURLOPT_HEADER, true );
        curl_setopt( $this->_ch, CURLOPT_CRLF, true );
        curl_setopt( $this->_ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $this->_ch, CURLOPT_FOLLOWLOCATION, true );

        if ( $this->beVerbose() ) {
            curl_setopt( $this->_ch, CURLOPT_VERBOSE, true );
        }

        $this->_initialised = true;
    }


    /**
     * Execute the actual cURL request
     *
     * @access private
     * @throws RestException if cURL not initialised, url not set
     * @return void
     */
    private function _runcURL() {
        $response   = curl_exec( $this->_ch );
        $info       = curl_getinfo( $this->_ch );
        $headerSize = curl_getinfo( $this->_ch, CURLINFO_HEADER_SIZE );

        $this->_response['header']    = substr( $response, 0, $headerSize );
        $this->_response['body']      = substr( $response, $headerSize );
        $this->_response['http_code'] = $info['http_code'];

        if ( $this->beVerbose() === true ) {
            $this->_response['verbose']['response'] = $response;
            $this->_response['verbose']['info']     = $info;
        }
    }


    /**
     * Stop our cURL instance
     *
     * @access private
     * @return void
     */
    private function _stopcURL() {
        if ( $this->cURLInitialised() ) {
            curl_close( $this->_ch );
        }
    }


    /**#@-*/


    /**#@+
     * Initialisation methods
     */

    /**
     * Initialise the URL of our cURL instance
     *
     * @access private
     * @throws RestException if cURL not initialised, URL not set
     * @return void
     * @param string  $path   REST API path to interact with
     * @param array   $params (optional) Additional parameters to pass with cURL request
     */
    private function _initURL( $path, $params = array() ) {
        if ( $this->cURLInitialised() === false ) {
            throw new RestException( self::EXCEPTION_CURL_NOT_INITIALISED );
        }

        if ( is_null( $this->_url ) || trim( $this->_url ) == '' ) {
            throw new RestException( self::EXCEPTION_REST_NO_URL );
        }

        $url = sprintf( '%s%s', $this->_url, $path );

        if ( is_array( $params ) && count( $params ) > 0 ) {
            /*
             * If we have additional parameters (usually a GET request), append the
             * additional parameters to the URL
             */
            $url .= sprintf( '?%s', http_build_query( $params ) );
        }

        // Otherwise (POST, PUT, DELETE), just set the URL
        curl_setopt( $this->_ch, CURLOPT_URL, $url );
    }


    /**
     * Initialise any additional HTTP headers to send with our request
     *
     * @access private
     * @throws RestException if cURL not initialised
     * @return void
     * @param array   $header (optional) Array of additional headers to send
     */
    private function _initHeader( $header = array() ) {
        if ( $this->cURLInitialised() === false ) {
            throw new RestException( self::EXCEPTION_CURL_NOT_INITIALISED );
        }

        if ( is_array( $header ) && count( $header ) > 0 ) {
            curl_setopt( $this->_ch, CURLOPT_HTTPHEADER, $header );
        }
    }


    /**
     * Initialise the username / password HTTP BASIC authorisation data
     *
     * @access private
     * @throws RestException if cURL not initialised
     * @return void
     * @param string  $user (optional) Username to set
     * @param string  $pass (optional) Password to set
     */
    private function _initAuthorisation( $user = null, $pass = null ) {
        if ( $this->cURLInitialised() === false ) {
            throw new RestException( self::EXCEPTION_CURL_NOT_INITIALISED );
        }

        if ( !is_null( $user ) && !is_null( $pass ) ) {
            curl_setopt( $this->_ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
            curl_setopt( $this->_ch, CURLOPT_USERPWD, sprintf( '%s:%s', $user, $pass ) );
        }
    }


    /**
     * Initialise the postfields
     *
     * @access private
     * @throws RestException if cURL is not initialised
     * @return void
     * @param mixed   $params (optional) Parameters to be sent with our cURL request
     */
    private function _initPostFields( $params ) {
        if ( $this->cURLInitialised() === false ) {
            throw new RestException( self::EXCEPTION_CURL_NOT_INITIALISED );
        }

        if ( is_array( $params ) && count( $params ) > 0 ) {
            curl_setopt( $this->_ch, CURLOPT_POSTFIELDS, $params );
        }
        else if ( !is_null( $params ) ) {
                curl_setopt( $this->_ch, CURLOPT_POSTFIELDS, sprintf( '@%s', $params ) );
            }
        else {
            curl_setopt( $this->_ch, CURLOPT_POSTFIELDS, $params );
        }
    }


    /**#@-*/


    /**#@+
     * Validation methods
     */

    /**
     * Ensure we have a valid HTTP request type
     *
     * @access private
     * @param string  $requestType Request type to check
     * @return bool
     */
    private function _isValidRequestType( $requestType ) {
        $validRequestTypes = array(
            self::REQUEST_TYPE_DELETE,
            self::REQUEST_TYPE_GET,
            self::REQUEST_TYPE_POST,
            self::REQUEST_TYPE_PUT,
        );

        return in_array( $requestType, $validRequestTypes );
    }


    /**
     * Ensure we have a valid response type
     *
     * @access private
     * @param string  $returnFormat Return format to check
     * @return bool
     */
    private function _isValidReturnFormat( $returnFormat ) {
        $validReturnFormats = array(
            self::RETURN_FORMAT_JSON,
        );

        return in_array( $returnFormat, $validReturnFormats );
    }


    /**
     * Check we have valid RestClient options
     *
     * @access private
     * @param string  $clientOption Client option to check
     * @return array
     */
    private function _isValidClientOption( $clientOption ) {
        $validClientOptions = array(
            self::OPTION_VERBOSE => array( true, false, ),
        );

        if ( array_key_exists( $clientOption, $validClientOptions ) ) {
            return $validClientOptions[$clientOption];
        }

        return array();
    }


    /**#@-*/

    /**
     * Parse RestClient options and action accordingly
     *
     * @access private
     * @return void
     * @param array   $options Options to parse and action
     */
    private function _parseOptions( $options ) {
        foreach ( $options as $key => $value ) {
            $optionValues = $this->_isValidClientOption( $key );

            if ( count( $optionValues ) > 0 && in_array( $value, $optionValues ) ) {
                // Value is valid, action the option
                switch ( $key ) {
                    case self::OPTION_VERBOSE:
                        $this->_verbose = $value;
                    break;

                    default:
                        // We already check the option is valid, so default case can do nothing
                    break;
                }
            }
        }
    }


    /**
     * Class destructor
     *
     * @access public
     */
    public function __destruct() {
        $this->_stopcURL();
    }


}


/**
 * RestException
 *
 * Exception class used for REST API Client
 *
 * @package    Utility
 * @subpackage REST
 */
class RestException extends Exception { }
