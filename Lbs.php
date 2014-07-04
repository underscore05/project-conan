<?php
class Lbs extends GlobeApi
{
    //PUBLIC VARIABLES
    public $version;
    public $recepient;
    public $address;
    public $accessToken;

    const CURL_URL = 'http://%s/location/%s/queries/location';

    /**
     * creates an sms
     *
     * @param string|null   $version        the api version to be used
     * @param string|null   $address        the shortcode
     */
    public function __construct(
        $address = null,
        $accessToken = null,
        $version = null
    ) {
        $this->version = $version;
        $this->address = $address;
        $this->accessToken = $accessToken;
    }

    public function locate() {
        $url = sprintf(
            Lbs::CURL_URL,
            GlobeAPI::API_ENDPOINT,
            $this->version,
            urlencode($this->address)
        );
        $fields = array(
            'access_token' => $this->accessToken,
            'address' => $this->address,
            'requestedAccuracy' => 100
        );
        $fields = array_filter($fields);

        $response = $this->_curlGet($url, $fields);
        return $this->getReturn($response, true);
    }
}