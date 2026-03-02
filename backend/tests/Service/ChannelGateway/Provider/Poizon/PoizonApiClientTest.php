<?php

declare(strict_types=1);

namespace App\Tests\Service\ChannelGateway\Provider\Poizon;

use App\Service\ChannelGateway\Exception\ChannelApiException;
use App\Service\ChannelGateway\Exception\ChannelAuthException;
use App\Service\ChannelGateway\Exception\ChannelRateLimitException;
use App\Service\ChannelGateway\Provider\Poizon\PoizonApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PoizonApiClientTest extends TestCase
{
    private PoizonApiClient $client;

    protected function setUp(): void
    {
        $this->client = new PoizonApiClient(
            $this->createMock(HttpClientInterface::class),
            new NullLogger(),
        );
    }

    // ========== createSign tests ==========

    public function testCreateSignBasic(): void
    {
        $params = ['app_key' => 'testkey', 'language' => 'en'];
        $appSecret = 'secret';

        // Both key and value are URL-encoded; ASCII-only values are unchanged by urlencode
        $expected = strtoupper(md5(
            urlencode('app_key').'='.urlencode('testkey').'&'.
            urlencode('language').'='.urlencode('en').
            $appSecret
        ));

        $sign = $this->client->createSign($params, $appSecret);

        $this->assertSame($expected, $sign);
        $this->assertSame(strtoupper($sign), $sign, 'Sign must be uppercase');
        $this->assertSame(32, strlen($sign), 'Sign must be 32 hex chars');
    }

    public function testCreateSignSortsKeys(): void
    {
        $params1 = ['app_key' => 'k', 'language' => 'en', 'version' => '1'];
        $params2 = ['version' => '1', 'app_key' => 'k', 'language' => 'en'];
        $appSecret = 'mysecret';

        $this->assertSame(
            $this->client->createSign($params1, $appSecret),
            $this->client->createSign($params2, $appSecret),
            'Sign must be identical regardless of insertion order'
        );
    }

    public function testCreateSignEncodesArrays(): void
    {
        $params = ['app_key' => 'k', 'brandIds' => [1, 2]];
        $appSecret = 'sec';

        // Array [1, 2] → JSON '[1,2]' → strip outer [] → '1,2' → urlencode → '1%2C2'
        $expected = strtoupper(md5(
            urlencode('app_key').'='.urlencode('k').'&'.
            urlencode('brandIds').'='.urlencode('1,2').
            $appSecret
        ));

        $sign = $this->client->createSign($params, $appSecret);

        $this->assertSame($expected, $sign);

        // Old (wrong) implementation would start with '[' → %5B; new must not
        $wrongExpected = strtoupper(md5('app_key=k&brandIds=[1,2]'.$appSecret));
        $this->assertNotSame($wrongExpected, $sign, 'Array values must not include outer brackets or be unencoded');
    }

    public function testCreateSignSkipsEmptyValues(): void
    {
        $paramsWithEmptyString = ['app_key' => 'k', 'language' => 'en', 'optional' => ''];
        $paramsWithNull = ['app_key' => 'k', 'language' => 'en', 'optional' => null];
        $paramsWithout = ['app_key' => 'k', 'language' => 'en'];
        $appSecret = 'sec';

        $baseline = $this->client->createSign($paramsWithout, $appSecret);

        $this->assertSame(
            $baseline,
            $this->client->createSign($paramsWithEmptyString, $appSecret),
            'Empty string values must be excluded from signature'
        );

        $this->assertSame(
            $baseline,
            $this->client->createSign($paramsWithNull, $appSecret),
            'Null values must be excluded from signature'
        );
    }

    public function testCreateSignExcludesSignKey(): void
    {
        $paramsWithSign = ['app_key' => 'k', 'language' => 'en', 'sign' => 'old_sign'];
        $paramsWithout = ['app_key' => 'k', 'language' => 'en'];
        $appSecret = 'sec';

        $this->assertSame(
            $this->client->createSign($paramsWithSign, $appSecret),
            $this->client->createSign($paramsWithout, $appSecret),
            'sign key must be excluded from signature input'
        );
    }

    // ========== request() tests ==========

    private function makeClient(MockResponse ...$responses): PoizonApiClient
    {
        return new PoizonApiClient(
            new MockHttpClient($responses, 'https://open.poizon.com'),
            new NullLogger(),
        );
    }

    public function testRequestAddsAppKeyAndSign(): void
    {
        $appKey = 'mykey';
        $appSecret = 'mysecret';

        $captured = [];
        $mockHttp = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode(['code' => 0, 'data' => []]), ['http_code' => 200]);
        });

        $client = new PoizonApiClient($mockHttp, new NullLogger());
        $result = $client->request($appKey, $appSecret, 'POST', '/api/v1/test', ['foo' => 'bar']);

        $this->assertSame(0, $result['code']);

        // Body sent as JSON
        $body = json_decode($captured['options']['body'], true);
        $this->assertSame($appKey, $body['app_key'], 'app_key must be in request body');
        $this->assertArrayHasKey('sign', $body, 'sign must be in request body');

        // sign is a 32-char uppercase hex string (timestamp makes exact value unpredictable)
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $body['sign'], 'sign must be 32 uppercase hex chars');
    }

    public function testRequestInjectsTimestamp(): void
    {
        $captured = [];
        $mockHttp = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['code' => 0, 'data' => []]), ['http_code' => 200]);
        });

        $before = (int) (microtime(true) * 1000);
        $client = new PoizonApiClient($mockHttp, new NullLogger());
        $client->request('key', 'secret', 'POST', '/api/v1/test', []);
        $after = (int) (microtime(true) * 1000);

        $this->assertArrayHasKey('timestamp', $captured, 'request body must contain timestamp');
        $this->assertIsInt($captured['timestamp'], 'timestamp must be an integer (milliseconds)');
        $this->assertGreaterThanOrEqual($before, $captured['timestamp'], 'timestamp must not be before request start');
        $this->assertLessThanOrEqual($after, $captured['timestamp'], 'timestamp must not be after request end');
    }

    public function testRequestGetSendsQueryParams(): void
    {
        $appKey = 'mykey';
        $appSecret = 'mysecret';

        $capturedUrl = '';
        $mockHttp = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse(json_encode(['code' => 0]), ['http_code' => 200]);
        });

        $client = new PoizonApiClient($mockHttp, new NullLogger());
        $client->request($appKey, $appSecret, 'GET', '/api/v1/test', ['page' => '1']);

        parse_str((string) parse_url($capturedUrl, PHP_URL_QUERY), $query);
        $this->assertSame($appKey, $query['app_key'], 'GET request must send app_key as query param');
        $this->assertArrayHasKey('sign', $query, 'GET request must send sign as query param');
    }

    public function testRequestThrowsChannelAuthExceptionOn401(): void
    {
        $client = $this->makeClient(
            new MockResponse(json_encode(['code' => 401, 'msg' => 'Unauthorized']), ['http_code' => 401])
        );

        $this->expectException(ChannelAuthException::class);
        $client->request('k', 's', 'POST', '/api/v1/test');
    }

    public function testRequestThrowsChannelAuthExceptionOn403(): void
    {
        $client = $this->makeClient(
            new MockResponse(json_encode(['code' => 403, 'msg' => 'Forbidden']), ['http_code' => 403])
        );

        $this->expectException(ChannelAuthException::class);
        $client->request('k', 's', 'POST', '/api/v1/test');
    }

    public function testRequestThrowsChannelRateLimitExceptionOn429(): void
    {
        $client = $this->makeClient(
            new MockResponse(json_encode(['code' => 429, 'msg' => 'Too Many Requests']), ['http_code' => 429])
        );

        $this->expectException(ChannelRateLimitException::class);
        $client->request('k', 's', 'POST', '/api/v1/test');
    }

    public function testRequestThrowsChannelApiExceptionOnOtherHttpError(): void
    {
        $client = $this->makeClient(
            new MockResponse(json_encode(['code' => 500, 'msg' => 'Internal Server Error']), ['http_code' => 500])
        );

        $this->expectException(ChannelApiException::class);
        $client->request('k', 's', 'POST', '/api/v1/test');
    }

    public function testRequestThrowsChannelApiExceptionOnNetworkError(): void
    {
        $client = $this->makeClient(
            new MockResponse('', ['http_code' => 200, 'error' => 'Connection refused'])
        );

        $this->expectException(ChannelApiException::class);
        $client->request('k', 's', 'POST', '/api/v1/test');
    }

    public function testGetBrandsByIdsCallsCorrectEndpoint(): void
    {
        $capturedUrl = '';
        $mockHttp = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse(json_encode(['code' => 0, 'data' => []]), ['http_code' => 200]);
        });

        $client = new PoizonApiClient($mockHttp, new NullLogger());
        $client->getBrandsByIds('k', 's', [1, 2]);

        $this->assertStringContainsString('/intl-commodity/intl/brand/query/by-id', $capturedUrl);
    }

    public function testGetBrandsByIdsPassesBrandIdsAndLanguage(): void
    {
        $captured = [];
        $mockHttp = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['code' => 0, 'data' => []]), ['http_code' => 200]);
        });

        $client = new PoizonApiClient($mockHttp, new NullLogger());
        $client->getBrandsByIds('k', 's', [5, 10], 'zh');

        $this->assertSame([5, 10], $captured['brandIds']);
        $this->assertSame('zh', $captured['language']);
    }
}
