<?php

namespace AusPost\Tests;

use PHPUnit\Framework\TestCase;
use AusPostApi;

class AusPostApiTest extends TestCase
{
    public function testMissingApiKeyReturnsError()
    {
        $api = new AusPostApi('');
        $result = $api->request('/postcode/search.json');

        $this->assertFalse($result);
        $this->assertStringContainsString('missing', strtolower($api->getLastError()));
    }

    public function testTestConnectionWithEmptyKey()
    {
        $api = new AusPostApi('');
        $res = $api->testConnection();

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('not configured', $res['message']);
    }

    public function testTestConnectionSuccessWithMock()
    {
        $api = new AusPostApi('fake-test-key-123');
        $api->setMockHandler(function ($url, $apiKey, $params) {
            $this->assertEquals('fake-test-key-123', $apiKey);
            $this->assertStringContainsString('postcode/search.json', $url);
            return array(
                'code' => 200,
                'body' => json_encode(array(
                    'localities' => array(
                        'locality' => array(
                            array('category' => 'Delivery Area', 'id' => 1, 'latitude' => -33.868, 'location' => 'SYDNEY', 'longitude' => 151.209, 'postcode' => 2000, 'state' => 'NSW')
                        )
                    )
                ))
            );
        });

        $res = $api->testConnection();
        $this->assertTrue($res['success']);
        $this->assertStringContainsString('successful', $res['message']);
    }

    public function testAuthFailure401HandledGracefully()
    {
        $api = new AusPostApi('invalid-key');
        $api->setMockHandler(function ($url, $apiKey, $params) {
            return array(
                'code' => 401,
                'error' => 'Authentication failed'
            );
        });

        $res = $api->request('/postage/parcel/domestic/service.json');
        $this->assertFalse($res);
        $this->assertStringContainsString('Authentication failed', $api->getLastError());
    }

    public function testGetDomesticServicesParsesBothSingleAndMultiResponses()
    {
        $api = new AusPostApi('test-key');

        // Multi-service mock
        $api->setMockHandler(function ($url, $apiKey, $params) {
            $this->assertEquals('2000', $params['from_postcode']);
            $this->assertEquals('3000', $params['to_postcode']);
            return array(
                'code' => 200,
                'body' => json_encode(array(
                    'services' => array(
                        'service' => array(
                            array('code' => 'AUS_PARCEL_REGULAR', 'name' => 'Parcel Post', 'price' => '10.60', 'max_extra_cover' => 5000),
                            array('code' => 'AUS_PARCEL_EXPRESS', 'name' => 'Express Post', 'price' => '14.10', 'max_extra_cover' => 5000),
                        )
                    )
                ))
            );
        });

        $services = $api->getDomesticServices('2000', '3000', 22, 16, 7.7, 1.5);
        $this->assertIsArray($services);
        $this->assertCount(2, $services);
        $this->assertEquals('AUS_PARCEL_REGULAR', $services[0]['code']);
        $this->assertEquals(10.60, $services[0]['price']);
        $this->assertEquals('AUS_PARCEL_EXPRESS', $services[1]['code']);
        $this->assertEquals(14.10, $services[1]['price']);

        // Single service mock (Australia Post XML-to-JSON sometimes wraps 1 item as object instead of list)
        $api->setMockHandler(function ($url, $apiKey, $params) {
            return array(
                'code' => 200,
                'body' => json_encode(array(
                    'services' => array(
                        'service' => array('code' => 'AUS_PARCEL_REGULAR', 'name' => 'Parcel Post', 'price' => '10.60')
                    )
                ))
            );
        });

        $single = $api->getDomesticServices('2000', '3000', 10, 10, 10, 0.5);
        $this->assertIsArray($single);
        $this->assertCount(1, $single);
        $this->assertEquals('AUS_PARCEL_REGULAR', $single[0]['code']);
    }

    public function testGetInternationalServices()
    {
        $api = new AusPostApi('test-key');
        $api->setMockHandler(function ($url, $apiKey, $params) {
            $this->assertEquals('NZ', $params['country_code']);
            return array(
                'code' => 200,
                'body' => json_encode(array(
                    'services' => array(
                        'service' => array(
                            array('code' => 'INT_PARCEL_STD', 'name' => 'International Standard', 'price' => '18.20'),
                            array('code' => 'INT_PARCEL_EXP', 'name' => 'International Express', 'price' => '32.50'),
                        )
                    )
                ))
            );
        });

        $services = $api->getInternationalServices('NZ', 1.0);
        $this->assertIsArray($services);
        $this->assertCount(2, $services);
        $this->assertEquals(18.20, $services[0]['price']);
    }
}
