<?php
require_once dirname(__FILE__) . '/../src/mixi.php';

class MixiTest extends PHPUnit_Framework_TestCase {
    public function  testGetUserBasic () {
        $mixi = new mixi  ( null, array () );
        $this->assertEquals ( null, $mixi->getUser ()  );
        $mixi->setAppData ( 'user_id' , 'hoge' );
        $this->assertEquals ( null, $mixi->getUser () );
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $this->assertEquals ( 'hoge', $mixi->getUser () );
    }

    public function  testGetUserException0 () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'clearAllAppData', 'clearStore', 'api'  ) )
            ->getMock();
        $mixi->expects ( $this->once() )->method ( 'clearAllAppData' );
        $mixi->expects ( $this->once() )->method ( 'clearStore' );
        $mixi->expects ( $this->once() )->method ( 'api' )
            ->with ( $this->equalTo ( '/people/@me/@self' ) )
            ->will ( $this->returnValue (
                (object)array (
                    'entry' => (object) array ( 'id' => 0 ) )
                ));
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $this->assertEquals ( null,  $mixi->getUser () );
    }

    public function  testGetUserException1 () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'clearAllAppData', 'clearStore' ) )
            ->getMock();
        $mixi->expects ( $this->once() )->method ( 'clearAllAppData' );
        $mixi->expects ( $this->once() )->method ( 'clearStore' );
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $mixi->setAppData ( 'user_id', 1 );
        $this->assertEquals ( null,  $mixi->getUser () );
    }

    public function  testGetUserExceptionTrue () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'clearAllAppData', 'clearStore' ) )
            ->getMock();
        $mixi->expects ( $this->once() )->method ( 'clearAllAppData' );
        $mixi->expects ( $this->once() )->method ( 'clearStore' );
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $mixi->setAppData ( 'user_id', true );
        $this->assertEquals ( null,  $mixi->getUser () );
    }

    public function  testGetUserExceptionFalse () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'clearAllAppData', 'clearStore', 'api' )  )
            ->getMock();
        $mixi->expects ( $this->once() )->method ( 'clearAllAppData' );
        $mixi->expects ( $this->once() )->method ( 'clearStore' );
        $mixi->expects ( $this->once() )->method ( 'api' )
            ->with ( $this->equalTo ( '/people/@me/@self' ) )
            ->will ( $this->returnValue (
                (object)array (
                    'entry' => (object) array ( 'id' => false ) )
                ));
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $mixi->setAppData ( 'user_id', false );
        $this->assertEquals ( null,  $mixi->getUser () );
    }

    public function  testGetUserExceptionMinus1 () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'clearAllAppData', 'clearStore' ) )
            ->getMock();
        $mixi->expects ( $this->once() )->method ( 'clearAllAppData' );
        $mixi->expects ( $this->once() )->method ( 'clearStore' );
        $mixi->setAppData ( 'user_id', -1 );
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $this->assertEquals ( null,  $mixi->getUser () );
    }

    public function testGetUserWithAPI () {
        $mixi = $this->getAPIStub ();
        $mixi->expects ( $this->once() )->method ( 'api' )
            ->with ( $this->equalTo ( '/people/@me/@self' ) )
            ->will ( $this->returnValue (
                (object)array (
                    'entry' => (object)array (
                        'id' => 'xyzzy'
                    ))));
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $this->assertEquals ( 'xyzzy', $mixi->getUser () );
    }

    public function testGetUserWithAPIFail () {
        $mixi = $this->getAPIStub ();
        $mixi->setAppData ( 'access_token' , 'fuga' );
        $mixi->expects ( $this->once() )->method ( 'api' )
            ->with ( $this->equalTo ( '/people/@me/@self' ) )
            ->will ( $this->returnValue ( null ));
        $this->assertEquals ( null, $mixi->getUser () );
    }

    public function  testGetScopeFromAppData () {
        $mixi = new mixi  ( null, array () );
        $this->assertEquals ( null, $mixi->getScopeFromAppData ()  );
        $mixi->setAppData ( 'scope' , 'hoge' );
        $this->assertEquals ( 'hoge', $mixi->getScopeFromAppData ()  );
    }

    public function  testGetTokenFromCode () {
        $mixi = $this->getRequestStub();
        $mixi->expects ( $this->once() )->method ( 'request' )
            ->with ( $this->equalTo ( 'POST' ), 'https://secure.mixi-platform.com/2/token', array (
                'grant_type' => 'authorization_code',
                'client_id' => 'key',
                'client_secret' => 'secret',
                'code' => 'code',
                'redirect_uri' => 'uri',
            ))
            ->will ( $this->returnValue ( json_encode ( array ( 'result' => 'ok') ) ) );
        $this->assertEquals ( 'ok', $mixi->getTokenFromCode( 'code' )->result  );
    }

    public function  testGetTokenFromCodeFail () {
        $mixi = $this->getRequestStub();
        $mixi->expects ( $this->once() )->method ( 'request' )
            ->with ( $this->equalTo ( 'POST' ), 'https://secure.mixi-platform.com/2/token', array (
                'grant_type' => 'authorization_code',
                'client_id' => 'key',
                'client_secret' => 'secret',
                'code' => 'code',
                'redirect_uri' => 'uri',
            ))
            ->will ( $this->returnValue ( ',fuga,piyo,hoge' ) );
        try {
            $mixi->getTokenFromCode( 'code' );
        }
        catch ( Exception $e ) {
            $this->assertEquals ( 'API Fail', $e->getMessage() );
        }
    }

    public function testCreateRequestResult () {
        $mixi = new mixi( null, array () ) ;
        $this->assertEquals ( 'fuga', $mixi->createRequestResult ( 'GET', 'url', array('p'), array('h'), 200, "hoge\n\nfuga"  ) );
        $this->assertEquals ( 'fuga', $mixi->createRequestResult ( 'GET', 'url', array('p'), array('h'), 200, "hoge\n.\nfuga" ) );
    }

    public function testCreateRequestResultExpiredToken () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor()
            ->setMethods ( array ( 'refreshAndRequest' ) )
            ->getMock();
        $mixi->expects ( $this->once() )->method ( 'refreshAndRequest' )
            ->with (
                $this->equalTo ( 'POST' ),
                $this->equalTo ( 'url' ),
                $this->equalTo ( array('p') ),
                $this->equalTo ( array('h') )
            )
            ->will ( $this->returnValue ( 'hoge,fuga,piyo' ) );
        $this->assertEquals ( 'hoge,fuga,piyo',
            $mixi->createRequestResult (
                'POST', 'url',
                array('p'),
                array('h'),
                401 ,
                "WWW-Authenticate: OAuth error='expired_token',realm='api.mixi-platform.com'"
            ));
    }

    public function testCreateRequestResultNotExpiredToken () {
        $mixi = new mixi(null, array());
        try {
            $mixi->createRequestResult ( 'POST', 'url', array('p'), array('h'), 401 , "hoge" );
        }catch ( Exception $e ) {
            $this->assertEquals ( 'API Error - httpcode:401|URL:url|params:array (  0 => \'p\',)|headers:array (  0 => \'h\',)|result:hoge', $e->getMessage () );
        }
        try {
            $mixi->createRequestResult ( 'POST', 'url', array('p'), array('h'), 402 , "hoge" );
        }catch ( Exception $e ) {
            $this->assertEquals ( 'API Error - httpcode:402|URL:url|params:array (  0 => \'p\',)|headers:array (  0 => \'h\',)|result:hoge', $e->getMessage () );
        }
        try {
            $mixi->createRequestResult ( 'POST', 'url', array('p'), array('h'), 403 , "hoge" );
        }catch ( Exception $e ) {
            $this->assertEquals ( 'API Error - httpcode:403|URL:url|params:array (  0 => \'p\',)|headers:array (  0 => \'h\',)|result:hoge', $e->getMessage () );
        }
        try {
            $mixi->createRequestResult ( 'POST', 'url', array('p'), array('h'), 404 , "hoge" );
        }catch ( Exception $e ) {
            $this->assertEquals ( 'API Error - httpcode:404|URL:url|params:array (  0 => \'p\',)|headers:array (  0 => \'h\',)|result:hoge', $e->getMessage () );
        }
    }

    public function testIsPageRequest () {
        $mixi = new mixi ( null, array() );
        $this->assertEquals ( false, $mixi->isPageRequest ( null ) );
        $this->assertEquals ( false, $mixi->isPageRequest ( array ( 'hoge' ) ) );
        $mixi->setPageAccessToken ( 'page' );
        $this->assertEquals ( false, $mixi->isPageRequest ( array ( 'hoge' ) ) );
        $this->assertEquals ( false, $mixi->isPageRequest ( array ( 'hoge', 'Authorization: OAuth user' ) ) );
        $this->assertEquals ( true, $mixi->isPageRequest ( array ( 'hoge', 'Authorization: OAuth page' ) ) );
    }

    public function testRefreshAndRequestForPage () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'isPageRequest', 'refreshPageAccessToken', 'getPageAccessToken', 'request' ) )
            ->getMock();

        $mixi->expects ( $this->once () )->method ( 'isPageRequest' )
            ->with ( $this->equalTo ( array ( 'header', 'Authorization: OAuth old' ) ) )
            ->will ( $this->returnValue ( true ) );

        $mixi->expects ( $this->once () )->method ( 'refreshPageAccessToken' );
        $mixi->expects ( $this->once () )->method ( 'getPageAccessToken' )
            ->will ( $this->returnValue ( 'renew' ) );
        $mixi->expects ( $this->once () )->method ( 'request' )
            ->with (
                $this->equalTo ( 'method' ),
                $this->equalTo ( 'url' ),
                $this->equalTo ( array ( 'p' ) ),
                $this->equalTo ( array (  'header', 'Authorization: OAuth renew' ) )
            )
            ->will ( $this->returnValue ( 'hoge' ) );
        $this->assertEquals ( 'hoge', $mixi->refreshAndRequest (
            'method', 'url', array ( 'p' ), array (  'header', 'Authorization: OAuth old' )
        ));
    }

    public function testRefreshAndRequestForUser () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array ( 'isPageRequest', 'refreshAccessToken', 'getAccessToken', 'request' ) )
            ->getMock();

        $mixi->expects ( $this->once () )->method ( 'isPageRequest' )
            ->with ( $this->equalTo ( array ( 'header', 'Authorization: OAuth old' ) ) )
            ->will ( $this->returnValue ( false ) );

        $mixi->expects ( $this->once () )->method ( 'refreshAccessToken' );
        $mixi->expects ( $this->once () )->method ( 'getAccessToken' )
            ->will ( $this->returnValue ( 'renew' ) );
        $mixi->expects ( $this->once () )->method ( 'request' )
            ->with (
                $this->equalTo ( 'method' ),
                $this->equalTo ( 'url' ),
                $this->equalTo ( array ( 'p' ) ),
                $this->equalTo ( array (  'header', 'Authorization: OAuth renew' ) )
            )
            ->will ( $this->returnValue ( 'hoge' ) );
        $this->assertEquals ( 'hoge', $mixi->refreshAndRequest (
            'method', 'url', array ( 'p' ), array (  'header', 'Authorization: OAuth old' )
        ));
    }

    public function testCreateCurlOptionsGET () {
        $expects = $this->getBasicCurlOptions();
        $expects[CURLOPT_URL]        = 'http://hoge.fuga.html?name=value';
        $expects[CURLOPT_HTTPHEADER] = array ( 'header1', 'header2' );
        $mixi = new mixi ( null, array () );
        $this->assertEquals (
            $expects,
            $mixi->createCurlOptions(
                'GET',
                'http://hoge.fuga.html',
                array ( 'name' => 'value' ),
                array ( 'header1', 'header2' )
            ));
    }

    public function testCreateCurlOptionsRawPOST () {
        $expects = $this->getBasicCurlOptions();
        $expects[CURLOPT_URL]        = 'http://hoge.fuga.html';
        $expects[CURLOPT_POST]       = 1;
        $expects[CURLOPT_POSTFIELDS] = 'RAWDATA';
        $expects[CURLOPT_HTTPHEADER] = array ( 'header1', 'header2' );
        $mixi = new mixi ( null, array () );
        $this->assertEquals (
            $expects,
            $mixi->createCurlOptions(
                'POST',
                'http://hoge.fuga.html',
                'RAWDATA',
                array ( 'header1', 'header2' )
            ));
    }

    public function testCreateCurlOptionsNormalPOST () {
        $expects = $this->getBasicCurlOptions();
        $expects[CURLOPT_URL]        = 'http://hoge.fuga.html';
        $expects[CURLOPT_POST]       = 1;
        $expects[CURLOPT_POSTFIELDS] = 'params1=1&params2=2';
        $mixi = new mixi ( null, array () );
        $this->assertEquals (
            $expects,
            $mixi->createCurlOptions(
                'POST',
                'http://hoge.fuga.html',
                array ( 'params1' => '1', 'params2' => '2' )
            ));
    }

    public function testCreateCurlOptionsRawArrayPOST () {
        $expects = $this->getBasicCurlOptions();
        $expects[CURLOPT_URL]        = 'http://hoge.fuga.html';
        $expects[CURLOPT_POST]       = 1;
        $expects[CURLOPT_POSTFIELDS] = array ( 'params1' => '1', 'params2' => '2' );
        $mixi = new mixi ( null, array () );
        $this->assertEquals (
            $expects,
            $mixi->createCurlOptions(
                'POST',
                'http://hoge.fuga.html',
                array ( 'rawarray' => array ( 'params1' => '1', 'params2' => '2' ) )
            ));
    }

    public function testCreateCurlOptionsWithRedirectUriPost () {
        $expects = $this->getBasicCurlOptions();
        $expects[CURLOPT_URL]        = 'http://hoge.fuga.html';
        $expects[CURLOPT_POST]       = 1;
        $expects[CURLOPT_POSTFIELDS] = 'url=' . urlencode ( 'http://hoge.fuga.com/?param=value' ) . '&redirect_uri=http://hoge.fuga.com/redirector';
        $mixi = new mixi ( null, array () );
        $this->assertEquals (
            $expects,
            $mixi->createCurlOptions(
                'POST',
                'http://hoge.fuga.html',
                array ( 'url' => 'http://hoge.fuga.com/?param=value', 'redirect_uri' => 'http://hoge.fuga.com/redirector' ) )
            );
    }

    public function testAPIGET () {
        $mixi = $this->getRequestStub ( );
        $mixi->expects ( $this->once() )->method ( 'request' )
            ->with (
                $this->equalTo ( 'GET' ),
                $this->equalTo ( 'http://api.mixi-platform.com/2/hoge' ),
                $this->equalTo ( array ( 'key1' => 'val1', 'key2' => 'val2' ) ),
                $this->equalTo ( array  ( 'Authorization: OAuth token' ) )
            )
            ->will ( $this->returnValue ( json_encode ( array ( 'result' => 'ok' ) ) ) );
        $mixi->setAccessToken ( 'token' );
        $result = $mixi->api ( '/hoge', array ( 'key1' => 'val1', 'key2' => 'val2' ) );
        $this->assertEquals ( 'ok', $result->result  );
    }

    public function testAPIPost () {
        $mixi = $this->getRequestStub ( );
        $mixi->expects ( $this->once() )->method ( 'request' )
            ->with (
                $this->equalTo ( 'POST' ),
                $this->equalTo ( 'http://api.mixi-platform.com/2/hoge' ),
                $this->equalTo ( array ( 'key1' => 'val1', 'key2' => 'val2' ) ),
                $this->equalTo ( array ( 'Content-type: application/x-www-form-urlencoded' ,'Authorization: OAuth token' ) )
            )
            ->will ( $this->returnValue ( json_encode ( array ( 'result' => 'ok' ) ) ) );
        $mixi->setAccessToken ( 'token' );
        $result = $mixi->api ( '/hoge', 'POST', array ( 'key1' => 'val1', 'key2' => 'val2' ) );
        $this->assertEquals ( 'ok', $result->result );
    }

    public function testAPIPostMulti () {
        $mixi = $this->getRequestStub ( );
        $mixi->expects ( $this->once() )->method ( 'request' )
            ->with (
                $this->equalTo ( 'POST' ),
                $this->equalTo ( 'http://api.mixi-platform.com/2/hoge' ),
                $this->equalTo ( array ( 'rawarray' => array ( 'key1' => 'val1', 'key2' => 'val2' ) ) ),
                $this->equalTo ( array ( 'Content-type: multipart/form-data' ,'Authorization: OAuth token' ) )
            )
            ->will ( $this->returnValue ( json_encode ( array ( 'result' => 'ok' ) ) ) );
        $mixi->setAccessToken ( 'token' );
        $result = $mixi->api ( '/hoge', 'POST/MULTI', array ( 'key1' => 'val1', 'key2' => 'val2' )  );
        $this->assertEquals ( 'ok', $result->result );
    }

    public function testAPIPostJSON () {
        $mixi = $this->getRequestStub ( );
        $mixi->expects ( $this->once() )->method ( 'request' )
            ->with (
                $this->equalTo ( 'POST' ),
                $this->equalTo ( 'http://api.mixi-platform.com/2/hoge' ),
                $this->equalTo ( 'json' ),
                $this->equalTo ( array ( 'Content-type: application/json; charset=utf8' ,'Authorization: OAuth token' ) )
            )
            ->will ( $this->returnValue ( json_encode ( array ( 'result' => 'ok' ) ) ) );
        $mixi->setAccessToken ( 'token' );
        $result = $mixi->api ( '/hoge', 'POST/JSON', 'json' );
        $this->assertEquals ( 'ok', $result->result );
    }

    public function testRefreshAccessToken () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array  ( 'request','setAuthenticationData' ) )
            ->getMock ();

        $mixi->expects ( $this->once () )->method ( 'request' )
            ->with (
                $this->equalTo ( 'POST' ),
                $this->equalTo ( 'https://secure.mixi-platform.com/2/token' ),
                $this->equalTo ( array  (
                    'grant_type' => 'refresh_token',
                    'client_id' => 'key',
                    'client_secret' => 'secret',
                    'refresh_token' => 'refresh'
                )))
                ->will( $this->returnValue ( json_encode ( array (
                    'access_token' => 'access_token',
                    'refresh_token' => 'refresh_token'
                ))));
        $mixi->expects ( $this->once() )->method ( 'setAuthenticationData' )
            ->with ( $this->equalTo ( (object)array (
                'access_token' => 'access_token',
                'refresh_token' => 'refresh_token',
            )));
        $mixi->setRefreshToken ( 'refresh' );
        $mixi->setConsumerKey( 'key' );
        $mixi->setConsumerSecret( 'secret' );
        $mixi->setRedirectUri( 'uri' );
        $mixi->refreshAccessToken( );
    }

    public function testRefreshAccessTokenFail () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor ()
            ->setMethods ( array  ( 'request' ) )
            ->getMock ();

        $mixi->expects ( $this->once () )->method ( 'request' )
            ->with (
                $this->equalTo ( 'POST' ),
                $this->equalTo ( 'https://secure.mixi-platform.com/2/token' ),
                $this->equalTo ( array  (
                    'grant_type' => 'refresh_token',
                    'client_id' => 'key',
                    'client_secret' => 'secret',
                    'refresh_token' => 'refresh'
                )))
                ->will( $this->returnValue ( 'hoge' ) );
        $mixi->setRefreshToken ( 'refresh' );
        $mixi->setConsumerKey( 'key' );
        $mixi->setConsumerSecret( 'secret' );
        $mixi->setRedirectUri( 'uri' );
        try {
            $mixi->refreshAccessToken( );
        } catch ( Exception $e ) {
            $this->assertEquals ( 'API Error', $e->getMessage () );
        }
    }

    ///
    /// HELPER
    ///
    public function getAPIStub () {
        return $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor()
            ->setMethods ( array ( 'api' ) )
            ->getMock ();
    }

    public function getRequestStub () {
        $mixi = $this->getMockBuilder ( 'mixi' )
            ->disableOriginalConstructor()
            ->setMethods ( array ( 'request' ) )
            ->getMock ();
        $mixi->setConsumerKey( 'key' );
        $mixi->setConsumerSecret( 'secret' );
        $mixi->setRedirectUri( 'uri' );
        return $mixi;
    }

    public function getBasicCurlOptions () {
        return array (
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => 1,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'mixi-php-sdk',
        );
    }

}

