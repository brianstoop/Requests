<?php

namespace WpOrg\Requests\Tests;

use WpOrg\Requests\Cookie;
use WpOrg\Requests\Cookie\Jar;
use WpOrg\Requests\Exception;
use WpOrg\Requests\Iri;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Response\Headers;
use WpOrg\Requests\Tests\TestCase;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

final class CookiesTest extends TestCase {
	public function testBasicCookie() {
		$cookie = new Cookie('requests-testcookie', 'testvalue');

		$this->assertSame('requests-testcookie', $cookie->name);
		$this->assertSame('testvalue', $cookie->value);
		$this->assertSame('testvalue', (string) $cookie);

		$this->assertSame('requests-testcookie=testvalue', $cookie->format_for_header());
		$this->assertSame('requests-testcookie=testvalue', $cookie->format_for_set_cookie());
	}

	public function testCookieWithAttributes() {
		$attributes = array(
			'httponly',
			'path' => '/',
		);
		$cookie     = new Cookie('requests-testcookie', 'testvalue', $attributes);

		$this->assertSame('requests-testcookie=testvalue', $cookie->format_for_header());
		$this->assertSame('requests-testcookie=testvalue; httponly; path=/', $cookie->format_for_set_cookie());
	}

	public function testEmptyCookieName() {
		$cookie = Cookie::parse('test');
		$this->assertSame('', $cookie->name);
		$this->assertSame('test', $cookie->value);
	}

	public function testEmptyAttributes() {
		$cookie = Cookie::parse('foo=bar; HttpOnly');
		$this->assertTrue($cookie->attributes['httponly']);
	}

	public function testCookieJarSetter() {
		$jar1                        = new Jar();
		$jar1['requests-testcookie'] = 'testvalue';

		$jar2 = new Jar(
			array(
				'requests-testcookie' => 'testvalue',
			)
		);
		$this->assertEquals($jar1, $jar2);
	}

	public function testCookieJarUnsetter() {
		$jar                        = new Jar();
		$jar['requests-testcookie'] = 'testvalue';

		$this->assertSame('testvalue', $jar['requests-testcookie']);

		unset($jar['requests-testcookie']);
		$this->assertEmpty($jar['requests-testcookie']);
		$this->assertFalse(isset($jar['requests-testcookie']));
	}

	public function testCookieJarAsList() {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Object is a dictionary, not a list');
		$cookies   = new Jar();
		$cookies[] = 'requests-testcookie1=testvalue1';
	}

	public function testCookieJarIterator() {
		$cookies = array(
			'requests-testcookie1' => 'testvalue1',
			'requests-testcookie2' => 'testvalue2',
		);
		$jar     = new Jar($cookies);

		foreach ($jar as $key => $value) {
			$this->assertSame($cookies[$key], $value);
		}
	}

	public function testReceivingCookies() {
		$options = array(
			'follow_redirects' => false,
		);
		$url     = httpbin('/cookies/set?requests-testcookie=testvalue');

		$response = Requests::get($url, array(), $options);

		$cookie = $response->cookies['requests-testcookie'];
		$this->assertNotEmpty($cookie);
		$this->assertSame('testvalue', $cookie->value);
	}

	public function testPersistenceOnRedirect() {
		$options = array(
			'follow_redirects' => true,
		);
		$url     = httpbin('/cookies/set?requests-testcookie=testvalue');

		$response = Requests::get($url, array(), $options);

		$cookie = $response->cookies['requests-testcookie'];
		$this->assertNotEmpty($cookie);
		$this->assertSame('testvalue', $cookie->value);
	}

	private function setCookieRequest($cookies) {
		$options  = array(
			'cookies' => $cookies,
		);
		$response = Requests::get(httpbin('/cookies/set'), array(), $options);

		$data = json_decode($response->body, true);
		$this->assertIsArray($data);
		$this->assertArrayHasKey('cookies', $data);
		return $data['cookies'];
	}

	public function testSendingCookie() {
		$cookies = array(
			'requests-testcookie1' => 'testvalue1',
		);

		$data = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie1', $data);
		$this->assertSame('testvalue1', $data['requests-testcookie1']);
	}

	/**
	 * @depends testSendingCookie
	 */
	public function testCookieExpiration() {
		$options = array(
			'follow_redirects' => true,
		);
		$url     = httpbin('/cookies/set/testcookie/testvalue');
		$url    .= '?expiry=1';

		$response = Requests::get($url, array(), $options);
		$response->throw_for_status();

		$data = json_decode($response->body, true);
		$this->assertEmpty($data['cookies']);
	}

	public function testSendingCookieWithJar() {
		$cookies = new Jar(
			array(
				'requests-testcookie1' => 'testvalue1',
			)
		);
		$data    = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie1', $data);
		$this->assertSame('testvalue1', $data['requests-testcookie1']);
	}

	public function testSendingMultipleCookies() {
		$cookies = array(
			'requests-testcookie1' => 'testvalue1',
			'requests-testcookie2' => 'testvalue2',
		);
		$data    = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie1', $data);
		$this->assertSame('testvalue1', $data['requests-testcookie1']);

		$this->assertArrayHasKey('requests-testcookie2', $data);
		$this->assertSame('testvalue2', $data['requests-testcookie2']);
	}

	public function testSendingMultipleCookiesWithJar() {
		$cookies = new Jar(
			array(
				'requests-testcookie1' => 'testvalue1',
				'requests-testcookie2' => 'testvalue2',
			)
		);
		$data    = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie1', $data);
		$this->assertSame('testvalue1', $data['requests-testcookie1']);

		$this->assertArrayHasKey('requests-testcookie2', $data);
		$this->assertSame('testvalue2', $data['requests-testcookie2']);
	}

	public function testSendingPrebakedCookie() {
		$cookies = new Jar(
			array(
				new Cookie('requests-testcookie', 'testvalue'),
			)
		);
		$data    = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie', $data);
		$this->assertSame('testvalue', $data['requests-testcookie']);
	}

	public function domainMatchProvider() {
		return array(
			array('example.com', 'example.com', true, true),
			array('example.com', 'www.example.com', false, true),
			array('example.com', 'example.net', false, false),

			// Leading period
			array('.example.com', 'example.com', true, true),
			array('.example.com', 'www.example.com', false, true),
			array('.example.com', 'example.net', false, false),

			// Prefix, but not subdomain
			array('example.com', 'notexample.com', false, false),
			array('example.com', 'notexample.net', false, false),

			// Reject IP address prefixes
			array('127.0.0.1', '127.0.0.1', true, true),
			array('127.0.0.1', 'abc.127.0.0.1', false, false),
			array('127.0.0.1', 'example.com', false, false),

			// Check that we're checking the actual length
			array('127.com', 'test.127.com', false, true),
		);
	}

	/**
	 * @dataProvider domainMatchProvider
	 */
	public function testDomainExactMatch($original, $check, $matches, $domain_matches) {
		$attributes           = new CaseInsensitiveDictionary();
		$attributes['domain'] = $original;
		$cookie               = new Cookie('requests-testcookie', 'testvalue', $attributes);
		$this->assertSame($matches, $cookie->domain_matches($check));
	}

	/**
	 * @dataProvider domainMatchProvider
	 */
	public function testDomainMatch($original, $check, $matches, $domain_matches) {
		$attributes           = new CaseInsensitiveDictionary();
		$attributes['domain'] = $original;
		$flags                = array(
			'host-only' => false,
		);
		$cookie               = new Cookie('requests-testcookie', 'testvalue', $attributes, $flags);
		$this->assertSame($domain_matches, $cookie->domain_matches($check));
	}

	public function pathMatchProvider() {
		return array(
			array('/', '', true),
			array('/', '/', true),

			array('/', '/test', true),
			array('/', '/test/', true),

			array('/test', '/', false),
			array('/test', '/test', true),
			array('/test', '/testing', false),
			array('/test', '/test/', true),
			array('/test', '/test/ing', true),
			array('/test', '/test/ing/', true),

			array('/test/', '/test/', true),
			array('/test/', '/', false),
		);
	}

	/**
	 * @dataProvider pathMatchProvider
	 */
	public function testPathMatch($original, $check, $matches) {
		$attributes         = new CaseInsensitiveDictionary();
		$attributes['path'] = $original;
		$cookie             = new Cookie('requests-testcookie', 'testvalue', $attributes);
		$this->assertSame($matches, $cookie->path_matches($check));
	}

	public function urlMatchProvider() {
		return array(
			// Domain handling
			array('example.com', '/', 'http://example.com/', true, true),
			array('example.com', '/', 'http://www.example.com/', false, true),
			array('example.com', '/', 'http://example.net/', false, false),
			array('example.com', '/', 'http://www.example.net/', false, false),

			// /test
			array('example.com', '/test', 'http://example.com/', false, false),
			array('example.com', '/test', 'http://www.example.com/', false, false),

			array('example.com', '/test', 'http://example.com/test', true, true),
			array('example.com', '/test', 'http://www.example.com/test', false, true),

			array('example.com', '/test', 'http://example.com/testing', false, false),
			array('example.com', '/test', 'http://www.example.com/testing', false, false),

			array('example.com', '/test', 'http://example.com/test/', true, true),
			array('example.com', '/test', 'http://www.example.com/test/', false, true),

			// /test/
			array('example.com', '/test/', 'http://example.com/', false, false),
			array('example.com', '/test/', 'http://www.example.com/', false, false),
		);
	}

	/**
	 * @depends testDomainExactMatch
	 * @depends testPathMatch
	 * @dataProvider urlMatchProvider
	 */
	public function testUrlExactMatch($domain, $path, $check, $matches, $domain_matches) {
		$attributes           = new CaseInsensitiveDictionary();
		$attributes['domain'] = $domain;
		$attributes['path']   = $path;
		$check                = new Iri($check);
		$cookie               = new Cookie('requests-testcookie', 'testvalue', $attributes);
		$this->assertSame($matches, $cookie->uri_matches($check));
	}

	/**
	 * @depends testDomainMatch
	 * @depends testPathMatch
	 * @dataProvider urlMatchProvider
	 */
	public function testUrlMatch($domain, $path, $check, $matches, $domain_matches) {
		$attributes           = new CaseInsensitiveDictionary();
		$attributes['domain'] = $domain;
		$attributes['path']   = $path;
		$flags                = array(
			'host-only' => false,
		);
		$check                = new Iri($check);
		$cookie               = new Cookie('requests-testcookie', 'testvalue', $attributes, $flags);
		$this->assertSame($domain_matches, $cookie->uri_matches($check));
	}

	public function testUrlMatchSecure() {
		$attributes           = new CaseInsensitiveDictionary();
		$attributes['domain'] = 'example.com';
		$attributes['path']   = '/';
		$attributes['secure'] = true;
		$flags                = array(
			'host-only' => false,
		);
		$cookie               = new Cookie('requests-testcookie', 'testvalue', $attributes, $flags);

		$this->assertTrue($cookie->uri_matches(new Iri('https://example.com/')));
		$this->assertFalse($cookie->uri_matches(new Iri('http://example.com/')));

		// Double-check host-only
		$this->assertTrue($cookie->uri_matches(new Iri('https://www.example.com/')));
		$this->assertFalse($cookie->uri_matches(new Iri('http://www.example.com/')));
	}

	/**
	 * Manually set cookies without a domain/path set should always be valid
	 *
	 * Cookies parsed from headers internally in Requests will always have a
	 * domain/path set, but those created manually will not. Manual cookies
	 * should be regarded as "global" cookies (that is, set for `.`)
	 */
	public function testUrlMatchManuallySet() {
		$cookie = new Cookie('requests-testcookie', 'testvalue');
		$this->assertTrue($cookie->domain_matches('example.com'));
		$this->assertTrue($cookie->domain_matches('example.net'));
		$this->assertTrue($cookie->path_matches('/'));
		$this->assertTrue($cookie->path_matches('/test'));
		$this->assertTrue($cookie->path_matches('/test/'));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.com/')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.com/test')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.com/test/')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.net/')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.net/test')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.net/test/')));
	}

	public static function parseResultProvider() {
		return array(
			// Basic parsing
			array(
				'foo=bar',
				array('name' => 'foo', 'value' => 'bar'),
			),
			array(
				'bar',
				array('name' => '', 'value' => 'bar'),
			),

			// Expiration
			// RFC 822, updated by RFC 1123
			array(
				'foo=bar; Expires=Thu, 5-Dec-2013 04:50:12 GMT',
				array('expired' => true),
				array('expires' => gmmktime(4, 50, 12, 12, 5, 2013)),
			),
			array(
				'foo=bar; Expires=Fri, 5-Dec-2014 04:50:12 GMT',
				array('expired' => false),
				array('expires' => gmmktime(4, 50, 12, 12, 5, 2014)),
			),
			// RFC 850, obsoleted by RFC 1036
			array(
				'foo=bar; Expires=Thursday, 5-Dec-2013 04:50:12 GMT',
				array('expired' => true),
				array('expires' => gmmktime(4, 50, 12, 12, 5, 2013)),
			),
			array(
				'foo=bar; Expires=Friday, 5-Dec-2014 04:50:12 GMT',
				array('expired' => false),
				array('expires' => gmmktime(4, 50, 12, 12, 5, 2014)),
			),
			// Test with asctime()
			array(
				'foo=bar; Expires=Thu Dec  5 04:50:12 2013',
				array('expired' => true),
				array('expires' => gmmktime(4, 50, 12, 12, 5, 2013)),
			),
			array(
				'foo=bar; Expires=Fri Dec  5 04:50:12 2014',
				array('expired' => false),
				array('expires' => gmmktime(4, 50, 12, 12, 5, 2014)),
			),
			array(
				// Invalid
				'foo=bar; Expires=never',
				array(),
				array('expires' => null),
			),

			// Max-Age
			array(
				'foo=bar; Max-Age=10',
				array('expired' => false),
				array('max-age' => gmmktime(0, 0, 10, 1, 1, 2014)),
			),
			array(
				'foo=bar; Max-Age=3660',
				array('expired' => false),
				array('max-age' => gmmktime(1, 1, 0, 1, 1, 2014)),
			),
			array(
				'foo=bar; Max-Age=0',
				array('expired' => true),
				array('max-age' => 0),
			),
			array(
				'foo=bar; Max-Age=-1000',
				array('expired' => true),
				array('max-age' => 0),
			),
			array(
				// Invalid (non-digit character)
				'foo=bar; Max-Age=1e6',
				array('expired' => false),
				array('max-age' => null),
			),
		);
	}

	private function check_parsed_cookie($cookie, $expected, $expected_attributes, $expected_flags = array()) {
		if (isset($expected['name'])) {
			$this->assertSame($expected['name'], $cookie->name);
		}
		if (isset($expected['value'])) {
			$this->assertSame($expected['value'], $cookie->value);
		}
		if (isset($expected['expired'])) {
			$this->assertSame($expected['expired'], $cookie->is_expired());
		}
		if (isset($expected_attributes)) {
			foreach ($expected_attributes as $attr_key => $attr_val) {
				$this->assertSame($attr_val, $cookie->attributes[$attr_key], "$attr_key should match supplied");
			}
		}
		if (isset($expected_flags)) {
			foreach ($expected_flags as $flag_key => $flag_val) {
				$this->assertSame($flag_val, $cookie->flags[$flag_key], "$flag_key should match supplied");
			}
		}
	}

	/**
	 * @dataProvider parseResultProvider
	 */
	public function testParsingHeader($header, $expected, $expected_attributes = array(), $expected_flags = array()) {
		// Set the reference time to 2014-01-01 00:00:00
		$reference_time = gmmktime(0, 0, 0, 1, 1, 2014);

		$cookie = Cookie::parse($header, null, $reference_time);
		$this->check_parsed_cookie($cookie, $expected, $expected_attributes);
	}

	/**
	 * Double-normalizes the cookie data to ensure we catch any issues there
	 *
	 * @dataProvider parseResultProvider
	 */
	public function testParsingHeaderDouble($header, $expected, $expected_attributes = array(), $expected_flags = array()) {
		// Set the reference time to 2014-01-01 00:00:00
		$reference_time = gmmktime(0, 0, 0, 1, 1, 2014);

		$cookie = Cookie::parse($header, null, $reference_time);

		// Normalize the value again
		$cookie->normalize();

		$this->check_parsed_cookie($cookie, $expected, $expected_attributes, $expected_flags);
	}

	/**
	 * @dataProvider parseResultProvider
	 */
	public function testParsingHeaderObject($header, $expected, $expected_attributes = array(), $expected_flags = array()) {
		$headers               = new Headers();
		$headers['Set-Cookie'] = $header;

		// Set the reference time to 2014-01-01 00:00:00
		$reference_time = gmmktime(0, 0, 0, 1, 1, 2014);

		$parsed = Cookie::parse_from_headers($headers, null, $reference_time);
		$this->assertCount(1, $parsed);

		$cookie = reset($parsed);
		$this->check_parsed_cookie($cookie, $expected, $expected_attributes);
	}

	public function parseFromHeadersProvider() {
		return array(
			# Varying origin path
			array(
				'name=value',
				'http://example.com/',
				array(),
				array('path' => '/'),
				array('host-only' => true),
			),
			array(
				'name=value',
				'http://example.com/test',
				array(),
				array('path' => '/'),
				array('host-only' => true),
			),
			array(
				'name=value',
				'http://example.com/test/',
				array(),
				array('path' => '/test'),
				array('host-only' => true),
			),
			array(
				'name=value',
				'http://example.com/test/abc',
				array(),
				array('path' => '/test'),
				array('host-only' => true),
			),
			array(
				'name=value',
				'http://example.com/test/abc/',
				array(),
				array('path' => '/test/abc'),
				array('host-only' => true),
			),

			# With specified path
			array(
				'name=value; path=/',
				'http://example.com/',
				array(),
				array('path' => '/'),
				array('host-only' => true),
			),
			array(
				'name=value; path=/test',
				'http://example.com/',
				array(),
				array('path' => '/test'),
				array('host-only' => true),
			),
			array(
				'name=value; path=/test/',
				'http://example.com/',
				array(),
				array('path' => '/test/'),
				array('host-only' => true),
			),

			# Invalid path
			array(
				'name=value; path=yolo',
				'http://example.com/',
				array(),
				array('path' => '/'),
				array('host-only' => true),
			),
			array(
				'name=value; path=yolo',
				'http://example.com/test/',
				array(),
				array('path' => '/test'),
				array('host-only' => true),
			),

			# Cross-origin cookies, reject!
			array(
				'name=value; domain=example.org',
				'http://example.com/',
				array('invalid' => false),
			),

			# Empty Domain
			array(
				'name=value; domain=',
				'http://example.com/test/',
				array(),
			),

			# Subdomain cookies
			array(
				'name=value; domain=test.example.com',
				'http://test.example.com/',
				array(),
				array('domain' => 'test.example.com'),
				array('host-only' => false),
			),
			array(
				'name=value; domain=example.com',
				'http://test.example.com/',
				array(),
				array('domain' => 'example.com'),
				array('host-only' => false),
			),
		);
	}

	/**
	 * @dataProvider parseFromHeadersProvider
	 */
	public function testParsingHeaderWithOrigin($header, $origin, $expected, $expected_attributes = array(), $expected_flags = array()) {
		$origin                = new Iri($origin);
		$headers               = new Headers();
		$headers['Set-Cookie'] = $header;

		// Set the reference time to 2014-01-01 00:00:00
		$reference_time = gmmktime(0, 0, 0, 1, 1, 2014);

		$parsed = Cookie::parse_from_headers($headers, $origin, $reference_time);
		if (isset($expected['invalid'])) {
			$this->assertCount(0, $parsed);
			return;
		}
		$this->assertCount(1, $parsed);

		$cookie = reset($parsed);
		$this->check_parsed_cookie($cookie, $expected, $expected_attributes, $expected_flags);
	}
}
