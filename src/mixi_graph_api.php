<?php
/**
 * mixi Graph APIを使うための色々様々
 */
abstract class MixiGraphAPI {

    public static $AUTHORIZE_URL = array(
        "pc" => 'https://mixi.jp/connect_authorize.pl',
        "mobile" => 'http://m.mixi.jp/connect_authorize.pl'
    );

    public static $API_ENDPOINT = 'http://api.mixi-platform.com';
    public static $API_VERSION = '2';

    public static $CURL_OPTIONS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => 1,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'mixi-php-sdk',
    );

    protected $consumer_key;
    protected $consumer_secret;
    protected $redirect_uri;
    protected $access_token;
    protected $refresh_token;
    protected $page_access_token  = null;
    protected $page_refresh_token = null;

    // user id
    protected $user;

    /**
     * コンストラクタ
     * @param array ( consumer_key, consumer_securet, scope, display, redirect_uri );
     */
    public function __construct ( $config )
    {
        $this->consumer_key = $config["consumer_key"];
        $this->consumer_secret = $config["consumer_secret"];
        $this->scope = $config["scope"];
        $this->display = ($config["display"]) ? $config["display"] : "pc";
        $this->redirect_uri = $config["redirect_uri"];

        //$this->sessionStart();
        /*
         * when changed scope need new authorization
         $scope = $this->getScopeFromAppData();
        if($scope != $this->scope){
            $this->clearAllAppData();
        }
         */
    }

    protected function sessionStart()
    {
        session_start();
    }


    /**
     * AppDataからuser_idを取得。無ければapiから取得。認可してないユーザーはnull
     * @return user_id
     */
    public function getUser()
    {
        if ( !$this->getAppData('access_token') ) return null;
        $user = null;
        if ( ! $user = $this->getAppData('user_id', $default = 0) ) {
            $user = null;
            try {
                $user = $this->api ( '/people/@me/@self' );
                if ( $user ) {
                    $this->setAppData( 'user_id', $user->entry->id );
                    $user = $this->getAppData ( 'user_id' );
                }
            }
            catch ( Exception $e ) {
                $this->clearAllAppData();
                $this->clearStore();
                $user = null;
            }
        }
        // さすがにこれは異常値なので
        if ( !is_null ( $user ) && ( $user === false || $user === true || $user === 0 || $user === 1 ||  $user === -1 ) )
        {
            $this->clearAllAppData();
            $this->clearStore();
            $user = null;
        }
        return $user;
    }

/*
    protected function getUserFromAccessToken()
    {
        $user = $this->api('/people/@me/@self');
        return $user->entry->id;
    }

    public function getAuthorizeURL($scope)
    {
        $url = self::$AUTHORIZE_URL[$this->display];
        $params = array(
            "client_id" => $this->consumer_key,
            "response_type" => "code",
            "scope" => $scope,
            "display" => $this->display,
            "state" => "",
        );
        $url .= '?' . http_build_query($params, null, '&');
        return $url;
    }

    protected function accessAuthorizeURL()
    {
        //var_dump("access authorize url");
        //$this->clearAllAppData();
        header("Location: " . $this->getAuthorizeURL($this->scope));
    }
*/

    /**
     * AppDataから設定されているscopeを返却する
     * @return scopeの値
     */
    public function getScopeFromAppData() {
        return $this->getAppData('scope');
    }

    /**
     * APIからトークンを取得する
     * @param コード
     * @return トークン
     */
    public function getTokenFromCode ( $code )
    {
        $url = 'https://secure.mixi-platform.com/2/token';
        $result = $this->request('POST', $url, array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->consumer_key,
            'client_secret' => $this->consumer_secret,
            'code' => $code,
            'redirect_uri' => $this->redirect_uri,
        ));

        $result =  json_decode($result);
        if ( !$result ) throw new Exception ( 'API Fail' );
        return $result;
    }

    /**
     * コードからトークンを取得して認可情報をAppDataに設定する
     * @param コード
     */
    public function onReceiveAuthorizationCode ( $code )
    {
        $token = $this->getTokenFromCode($code);
        $this->setAuthenticationData($token);
    }

    /**
     * 認可情報(トークン、リフレッシュトークン、トークンに許可されたスコープ、ユーザーID)をAppDataに設定する
     * @param (stdClass)トークン(access_token, refresh_token )
     */
    public function setAuthenticationData($token){
        $this->setAppData ( 'access_token' , $this->access_token  = $token->access_token );
        $this->setAppData ( 'refresh_token', $this->refresh_token = $token->refresh_token );
        $this->setAppData ( 'scope', $token->scope );
        $user = $this->getUser();
        $this->setAppData('user_id', $user);
    }

    /**
     * リクエストする
     * @param http_method (GET|POST|DELETE|PUT|HEAD)
     * @param url リクエスト先のURL
     * @param params リクエストパラメータ
     * @param headers 追加するヘッダ(optional)
     * @return 結果
     */
    public function request ( $method, $url, $params, $headers = array () )
    {
        $curl    = curl_init();
        $options   = $this->createCurlOptions ( $method, $url, $params, $headers );
        curl_setopt_array($curl, $options);
        $result    = curl_exec($curl);
        $curl_info = curl_getinfo($curl);
        $http_code = $curl_info["http_code"];
        curl_close ( $curl );

        return $this->createRequestResult ( $method, $url, $params, $headers, $http_code, $result );
    }

    public function createRequestResult ( $method, $url, $params, $headers, $http_code, $result ) {
        if (  $http_code >= 200 && $http_code < 300 ) {
            //[note]改行と改行の間にゴミが混じっているときがある
            list ( $header, $body ) = preg_split("/\n.?\n/", $result);
            return $body;
        }
        else {
            if($http_code == 401){
                preg_match ( "/WWW-Authenticate: OAuth error='(.*?)'/", $result, $match );
                $error_message = ($match && $match[1]) ? $match[1] : null;
                if( preg_match ( "#expired_token#", $error_message ) ) {
                    return $this->refreshAndRequest ( $method, $url, $params, $headers );
                }
            }
            throw new Exception (
                'API Error - ' .
                'httpcode:' . $http_code                    . '|' .
                'URL:'      . $url                          . '|' .
                'params:'   . str_replace ( "\n", "", var_export ( $params , true ) ). '|' .
                'headers:'  . str_replace ( "\n", "", var_export ( $headers, true ) ). '|' .
                'result:'   . $result
            );
        }
    }

    public function isPageRequest ( $headers ) {
        if ( !$headers ) return false;
        $page_token = $this->getPageAccessToken();
        if ( !$page_token ) return false;
        return count ( preg_grep (
            '#^Authorization: OAuth ' . preg_quote ( $page_token, '#' ) . '$#',
            $headers
        )) > 0;
    }

    public function refreshAndRequest ( $method, $url, $params, $headers ) {
        $token = null;
        if ( $this->isPageRequest ( $headers ) ) {
            $this->refreshPageAccessToken();
            $token = $this->getPageAccessToken();
        }
        else {
            $this->refreshAccessToken();
            $token = $this->getAccessToken();
        }
        return $this->request ( $method, $url, $params, array_map (
            function ( $row ) use ( $token ) {
                return preg_match ( '#^Authorization: OAuth#', $row ) ?
                    'Authorization: OAuth '. $token : $row;
            }, $headers ));
    }

    /**
     * リクエストする curlのオプション作成する
     * @param http_method (GET|POST|DELETE|PUT|HEAD)
     * @param url リクエスト先のURL
     * @param params リクエストパラメータ
     * @param headers 追加するヘッダ(optional)
     * @return 結果
     */
    public function createCurlOptions ( $method, $url, $params, $headers = array () ) {
        $options = self::$CURL_OPTIONS;
        // 通常のPOST
        if ( $method == 'POST' && is_array ( $params ) ) {
            $options[CURLOPT_POST] = 1;
            if ( isset ( $params['redirect_uri'] ) ) {
                $tmp = array();
                // NOTE: mixiではredirect_uri を URLエンコードしてはならない
                foreach ( $params as $key => $value ) {
                    if ( $key != 'redirect_uri' ) {
                        $tmp[$key] = $value;
                    }
                }
                $options[CURLOPT_POSTFIELDS] = http_build_query ( $tmp, null, '&' ) . '&redirect_uri=' . $params['redirect_uri'];
            }
            elseif ( isset ( $params['rawarray'] ) ) {
                $options[CURLOPT_POSTFIELDS] = $params['rawarray'];
            }
            else {
                $options[CURLOPT_POSTFIELDS] = http_build_query ( $params, null, '&' );
            }
        }
        // 生データを突っ込む (画像を突っ込む場合など)
        elseif ( $method == 'POST' && $params ) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $params;
        }
        elseif ( $method == 'GET' && $params ) {
            $url .= "?" . http_build_query ( $params, null, '&' );
        }
        $options[CURLOPT_URL] = $url;
        if ( $headers ) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        return $options;
    }

    /**
     * APIリクエストする
     * @param pthh リクエスト先
     * @param http_method (GET|POST|DELETE|PUT|HEAD)
     * @param params リクエストパラメータ
     * @param headers 追加するヘッダ(optional)
     * @return 結果のJSONをデコードした値
     */
    public function api ( $path, $method = 'GET', $params = null, $headers = array() ) {
        if(!$path) return FALSE;
        $url = self::$API_ENDPOINT . "/" . self::$API_VERSION;
        $url .= $path;
        if ( is_array ( $method ) && empty ( $params ) )
        {
            $params = $method;
            $method = 'GET';
        }

        if(!$headers && $method == "POST"){
            array_push($headers, "Content-type: application/x-www-form-urlencoded");
        }
        elseif(!$headers && $method == "POST/MULTI"){
            $method = 'POST';
            array_push($headers, "Content-type: multipart/form-data");
            if ( is_array ( $params ) ) $params = array ( 'rawarray' => $params );
        }
        elseif(!$headers && $method == "POST/JSON"){
            $method = 'POST';
            array_push($headers, "Content-type: application/json; charset=utf8");
        }
        array_push($headers, "Authorization: OAuth " . $this->getAccessToken());

        return json_decode($this->request($method, $url, $params, $headers));
    }

    /**
     * リフレッシュトークンからAccessTokenをリフレッシュする
     */
    public function refreshAccessToken()
    {
        $url = 'https://secure.mixi-platform.com/2/token';
        $result = $this->request('POST', $url, array(
            'grant_type' => 'refresh_token',
            'client_id' => $this->consumer_key,
            'client_secret' => $this->consumer_secret,
            'refresh_token' => $this->getRefreshToken(),
        ));
        $token = json_decode($result);
        //if(!$token) return $this->accessAuthorizeURL();
        if ( !$token ) throw new Exception ( 'API Error' );
        $this->setAuthenticationData($token);
    }

    public function getAccessTokenFromAppData ()
    {
        $app_access_token = $this->getAppData('access_token');
        if($app_access_token){
            $this->setAccessToken($app_access_token);
            return $app_access_token;
        }
        //$this->accessAuthorizeURL();
    }

    public function getRefreshTokenFromAppData()
    {
        $app_refresh_token = $this->getAppData('refresh_token');
        if($app_refresh_token){
            $this->setRefreshToken($app_refresh_token);
            return $app_refresh_token;
        }
        //$this->accessAuthorizeURL();
    }

    public function getAccessToken()
    {
        if ( $this->access_token )
        {
            return $this->access_token;
        }
        return $this->getAccessTokenFromAppData();
    }

    public function setAccessToken( $access_token )
    {
        $this->access_token = $access_token;
    }

    public function getRefreshToken()
    {
        if ( $this->refresh_token )
        {
            return $this->refresh_token;
        }
        return $this->getRefreshTokenFromAppData();
    }

    public function setRefreshToken ( $refresh_token )
    {
        $this->refresh_token = $refresh_token;
    }

    abstract public function setAppData($key, $value);

    abstract protected function getAppData($key, $default = false);

    abstract protected function clearAppData($key);

    abstract protected function clearAllAppData();


    public function setConsumerKey ( $val )
    {
        $this->consumer_key    = $val;
    }

    public function setConsumerSecret ( $val )
    {
        $this->consumer_secret = $val;
    }

    public function setScope ( $val )
    {
        $this->scope           = $val;
    }

    public function setDisplay ( $val )
    {
        $this->display         = $val;
    }

    public function setRedirectUri ( $val )
    {
        $this->redirect_uri    =  $val;
    }

    public function fillPageAccessToken ()
    {
        $url = 'https://secure.mixi-platform.com/2/token';
        $result = $this->request('POST', $url, array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->consumer_key,
            'client_secret' => $this->consumer_secret,
        ));
        $token = json_decode($result);
        $this->setPageAuthenticationData($token);
    }

    public function setPageAuthenticationData($token)
    {
        $this->setAppData ( 'page_access_token' , $this->page_access_token  = $token->access_token );
        $this->setAppData ( 'page_refresh_token', $this->page_refresh_token = $token->refresh_token );
    }

    public function pageApi ( $path, $method = 'GET', $params = null, $headers = array() )
    {
        if(!$path) return FALSE;
        $url = self::$API_ENDPOINT . "/" . self::$API_VERSION;
        $url .= $path;
        if ( is_array ( $method ) && empty ( $params ) )
        {
            $params = $method;
            $method = 'GET';
        }
        array_push ($headers, "Authorization: OAuth " . $this->getPageAccessToken() );
        return json_decode($this->request($method, $url, $params, $headers));
    }

    public function refreshPageAccessToken()
    {
        $url = 'https://secure.mixi-platform.com/2/token';
        $result = $this->request('POST', $url, array(
            'grant_type' => 'refresh_token',
            'client_id' => $this->consumer_key,
            'client_secret' => $this->consumer_secret,
            'refresh_token' => $this->getPageRefreshToken(),
        ));
        $token = json_decode($result);
        $this->setPageAuthenticationData($token);
    }

    public function getPageRefreshToken()
    {
        if ( $this->page_refresh_token )
        {
            return $this->page_refresh_token;
        }
        return $this->getPageRefreshTokenFromAppData();
    }

    public function getPageRefreshTokenFromAppData()
    {
        $app_refresh_token = $this->getAppData('page_refresh_token');
        if($app_refresh_token){
            $this->setPageRefreshToken($app_refresh_token);
            return $app_refresh_token;
        }
    }

    public function setPageRefreshToken ( $refresh_token )
    {
        $this->page_refresh_token = $refresh_token;
    }

    public function getPageAccessToken()
    {
        if ( $this->page_access_token )
        {
            return $this->page_access_token;
        }
        return $this->getPageAccessTokenFromAppData();
    }

    public function getPageAccessTokenFromAppData()
    {
        $app_access_token = $this->getAppData('page_access_token');
        if($app_access_token)
        {
            $this->setPageAccessToken($app_access_token);
            return $app_access_token;
        }
    }

    public function setPageAccessToken ( $access_token )
    {
        $this->page_access_token = $access_token;
    }


}
