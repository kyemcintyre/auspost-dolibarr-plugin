<?php
/**
 * Australia Post Postage Assessment Calculator (PAC) API Client
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

if (!defined('AUSPOST_API_BASE_URL_DEFAULT')) {
    define('AUSPOST_API_BASE_URL_DEFAULT', 'https://digitalapi.auspost.com.au');
}

class AusPostApi
{
    /** @var string */
    protected $apiKey;

    /** @var string */
    protected $baseUrl;

    /** @var int Timeout in seconds */
    protected $timeout = 10;

    /** @var string|null Last error message */
    protected $lastError = null;

    /** @var int|null Last HTTP status code */
    protected $lastHttpCode = null;

    /** @var mixed Last raw response body */
    protected $lastRawResponse = null;

    /** @var callable|null Mock handler for unit testing */
    protected $mockHandler = null;

    /**
     * Constructor
     *
     * @param string|null $apiKey  Australia Post API Key
     * @param string|null $baseUrl Australia Post API Base URL
     */
    public function __construct($apiKey = null, $baseUrl = null)
    {
        $this->apiKey = $apiKey !== null ? trim($apiKey) : auspost_get_api_key();
        $this->baseUrl = $baseUrl !== null ? rtrim(trim($baseUrl), '/') : auspost_get_api_base_url();
    }

    /**
     * Set a mock response handler for unit testing
     *
     * @param callable|null $handler
     * @return self
     */
    public function setMockHandler($handler)
    {
        $this->mockHandler = $handler;
        return $this;
    }

    /**
     * Set timeout in seconds
     *
     * @param int $timeout
     * @return self
     */
    public function setTimeout($timeout)
    {
        $this->timeout = max(1, (int)$timeout);
        return $this;
    }

    /**
     * Get the last error message
     *
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get the last HTTP status code
     *
     * @return int|null
     */
    public function getLastHttpCode()
    {
        return $this->lastHttpCode;
    }

    /**
     * Get the last raw response
     *
     * @return mixed
     */
    public function getLastRawResponse()
    {
        return $this->lastRawResponse;
    }

    /**
     * Test connection to Australia Post API with current credentials
     *
     * @return array array('success' => bool, 'message' => string, 'latency_ms' => float)
     */
    public function testConnection()
    {
        if (empty($this->apiKey)) {
            return array(
                'success' => false,
                'message' => 'Australia Post API key is not configured.',
                'latency_ms' => 0
            );
        }

        $startTime = microtime(true);
        // Fast test call using postcode search
        $response = $this->request('/postcode/search.json', array('q' => '2000'));
        $latency = round((microtime(true) - $startTime) * 1000, 1);

        if ($response === false) {
            return array(
                'success' => false,
                'message' => $this->lastError ?: 'Failed to connect to Australia Post API.',
                'latency_ms' => $latency
            );
        }

        return array(
            'success' => true,
            'message' => 'Connection successful. Australia Post PAC API is responding correctly.',
            'latency_ms' => $latency
        );
    }

    /**
     * Search postcode and localities in Australia
     *
     * @param string $query Postcode (e.g. 2000) or suburb name (e.g. Sydney)
     * @param string $state State filter (e.g. NSW, VIC, QLD)
     * @return array|false List of localities or false on error
     */
    public function searchPostcode($query, $state = '')
    {
        $params = array('q' => trim($query));
        if (!empty($state)) {
            $params['state'] = strtoupper(trim($state));
        }

        $response = $this->request('/postcode/search.json', $params);
        if ($response === false || !isset($response['localities'])) {
            return false;
        }

        if (empty($response['localities']['locality'])) {
            return array();
        }

        $localities = $response['localities']['locality'];
        // Ensure always an array of items
        if (isset($localities['postcode'])) {
            $localities = array($localities);
        }

        return $localities;
    }

    /**
     * Get available domestic parcel delivery services and prices
     *
     * @param string $fromPostcode Origin postcode (e.g. 2000)
     * @param string $toPostcode   Destination postcode (e.g. 3000)
     * @param float  $length       Length in cm
     * @param float  $width        Width in cm
     * @param float  $height       Height in cm
     * @param float  $weight       Weight in kg
     * @return array|false List of services or false on error
     */
    public function getDomesticServices($fromPostcode, $toPostcode, $length, $width, $height, $weight)
    {
        $params = array(
            'from_postcode' => trim($fromPostcode),
            'to_postcode'   => trim($toPostcode),
            'length'        => max(1.0, round((float)$length, 1)),
            'width'         => max(1.0, round((float)$width, 1)),
            'height'        => max(1.0, round((float)$height, 1)),
            'weight'        => max(0.05, round((float)$weight, 3)),
        );

        $response = $this->request('/postage/parcel/domestic/service.json', $params);
        if ($response === false) {
            return false;
        }

        if (!isset($response['services']) || !isset($response['services']['service'])) {
            $this->lastError = 'No domestic shipping services returned for the specified package.';
            return false;
        }

        $services = $response['services']['service'];
        // Normalize single service into list
        if (isset($services['code'])) {
            $services = array($services);
        }

        $normalized = array();
        foreach ($services as $svc) {
            $code = isset($svc['code']) ? $svc['code'] : '';
            $name = isset($svc['name']) ? $svc['name'] : $code;
            $price = isset($svc['price']) ? (float)$svc['price'] : 0.0;
            $maxExtraCover = isset($svc['max_extra_cover']) ? (float)$svc['max_extra_cover'] : 0.0;

            // Extract available options if present
            $options = array();
            if (isset($svc['options']) && isset($svc['options']['option'])) {
                $opts = $svc['options']['option'];
                if (isset($opts['code'])) {
                    $opts = array($opts);
                }
                foreach ($opts as $opt) {
                    $options[] = array(
                        'code' => isset($opt['code']) ? $opt['code'] : '',
                        'name' => isset($opt['name']) ? $opt['name'] : '',
                    );
                }
            }

            $normalized[] = array(
                'code'            => $code,
                'name'            => $name,
                'price'           => $price,
                'max_extra_cover' => $maxExtraCover,
                'options'         => $options,
            );
        }

        return $normalized;
    }

    /**
     * Calculate domestic parcel cost with specific service and optional extras
     *
     * @param string $fromPostcode Origin postcode
     * @param string $toPostcode   Destination postcode
     * @param float  $length       Length in cm
     * @param float  $width        Width in cm
     * @param float  $height       Height in cm
     * @param float  $weight       Weight in kg
     * @param string $serviceCode  AusPost service code (e.g. AUS_PARCEL_REGULAR)
     * @param array  $options      Optional extra codes (e.g. AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY)
     * @param float  $extraCover   Optional extra cover monetary value
     * @return array|false
     */
    public function calculateDomesticService(
        $fromPostcode,
        $toPostcode,
        $length,
        $width,
        $height,
        $weight,
        $serviceCode,
        $options = array(),
        $extraCover = 0.0
    ) {
        $params = array(
            'from_postcode' => trim($fromPostcode),
            'to_postcode'   => trim($toPostcode),
            'length'        => max(1.0, round((float)$length, 1)),
            'width'         => max(1.0, round((float)$width, 1)),
            'height'        => max(1.0, round((float)$height, 1)),
            'weight'        => max(0.05, round((float)$weight, 3)),
            'service_code'  => trim($serviceCode),
        );

        if (!empty($options)) {
            $params['option_code'] = is_array($options) ? implode(';', $options) : $options;
        }

        if ($extraCover > 0) {
            $params['extra_cover'] = round((float)$extraCover, 2);
        }

        $response = $this->request('/postage/parcel/domestic/calculate.json', $params);
        if ($response === false) {
            return false;
        }

        if (!isset($response['postage_result'])) {
            $this->lastError = 'Invalid response format from Australia Post calculation endpoint.';
            return false;
        }

        $res = $response['postage_result'];
        return array(
            'service_code' => isset($res['service_code']) ? $res['service_code'] : $serviceCode,
            'total_cost'   => isset($res['total_cost']) ? (float)$res['total_cost'] : 0.0,
            'costs'        => isset($res['costs']) ? $res['costs'] : array(),
        );
    }

    /**
     * Get available international parcel services and prices
     *
     * @param string $countryCode 2-character ISO country code (e.g. NZ, US, GB)
     * @param float  $weight      Weight in kg
     * @return array|false List of services or false on error
     */
    public function getInternationalServices($countryCode, $weight)
    {
        $params = array(
            'country_code' => strtoupper(trim($countryCode)),
            'weight'       => max(0.05, round((float)$weight, 3)),
        );

        $response = $this->request('/postage/parcel/international/service.json', $params);
        if ($response === false) {
            return false;
        }

        if (!isset($response['services']) || !isset($response['services']['service'])) {
            $this->lastError = 'No international shipping services returned for the specified destination.';
            return false;
        }

        $services = $response['services']['service'];
        if (isset($services['code'])) {
            $services = array($services);
        }

        $normalized = array();
        foreach ($services as $svc) {
            $code = isset($svc['code']) ? $svc['code'] : '';
            $name = isset($svc['name']) ? $svc['name'] : $code;
            $price = isset($svc['price']) ? (float)$svc['price'] : 0.0;
            $maxExtraCover = isset($svc['max_extra_cover']) ? (float)$svc['max_extra_cover'] : 0.0;

            $normalized[] = array(
                'code'            => $code,
                'name'            => $name,
                'price'           => $price,
                'max_extra_cover' => $maxExtraCover,
            );
        }

        return $normalized;
    }

    /**
     * Get list of countries supported by Australia Post international parcel service
     *
     * @return array|false
     */
    public function getCountries()
    {
        $response = $this->request('/postage/country.json');
        if ($response === false || !isset($response['countries'])) {
            return false;
        }

        $countries = isset($response['countries']['country']) ? $response['countries']['country'] : array();
        if (isset($countries['code'])) {
            $countries = array($countries);
        }

        return $countries;
    }

    /**
     * Send HTTP GET request to Australia Post PAC API
     *
     * @param string $endpoint API endpoint path (e.g. /postage/parcel/domestic/service.json)
     * @param array  $params   Query parameters
     * @return array|false Decoded JSON response or false on error
     */
    public function request($endpoint, $params = array())
    {
        $this->lastError = null;
        $this->lastHttpCode = null;
        $this->lastRawResponse = null;

        if (empty($this->apiKey)) {
            $this->lastError = 'Australia Post API key is missing. Please configure it in the module settings.';
            return false;
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        // Check if mock handler is set for testing
        if ($this->mockHandler !== null && is_callable($this->mockHandler)) {
            $mockResult = call_user_func($this->mockHandler, $url, $this->apiKey, $params);
            $this->lastHttpCode = isset($mockResult['code']) ? $mockResult['code'] : 200;
            $this->lastRawResponse = isset($mockResult['body']) ? $mockResult['body'] : '';

            if ($this->lastHttpCode !== 200) {
                $this->lastError = isset($mockResult['error']) ? $mockResult['error'] : 'HTTP ' . $this->lastHttpCode;
                return false;
            }

            return json_decode($this->lastRawResponse, true);
        }

        if (!function_exists('curl_init')) {
            $this->lastError = 'cURL extension is required for Australia Post API calls.';
            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => array(
                'AUTH-KEY: ' . $this->apiKey,
                'Accept: application/json',
                'User-Agent: Dolibarr-AusPost-Plugin/1.0',
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));

        $rawBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        $this->lastHttpCode = $httpCode;
        $this->lastRawResponse = $rawBody;

        if ($curlErrno !== 0) {
            $this->lastError = 'Network error connecting to Australia Post API: ' . $curlError;
            return false;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            $this->lastError = 'Authentication failed (HTTP ' . $httpCode . '). Invalid or unauthorized Australia Post API key.';
            return false;
        }

        if ($httpCode === 404) {
            $this->lastError = 'The requested resource or location could not be found by Australia Post (HTTP 404).';
            return false;
        }

        if ($httpCode >= 500) {
            $this->lastError = 'Australia Post server error (HTTP ' . $httpCode . '). Please try again later.';
            return false;
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($rawBody, true);
            $errDetail = '';
            if (isset($decoded['error']) && isset($decoded['error']['errorMessage'])) {
                $errDetail = ': ' . $decoded['error']['errorMessage'];
            }
            $this->lastError = 'Australia Post API error (HTTP ' . $httpCode . ')' . $errDetail;
            return false;
        }

        $decoded = json_decode($rawBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Failed to parse Australia Post JSON response: ' . json_last_error_msg();
            return false;
        }

        return $decoded;
    }
}
