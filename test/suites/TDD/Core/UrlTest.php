<?php
/**
 * @license see LICENSE
 */
namespace Serps\Test\TDD\Core;

use Serps\Core\Cookie\Cookie;
use Serps\Core\Url;
use Serps\Core\UrlArchive;

/**
 * @covers Serps\Core\Url
 * @covers Serps\Core\UrlArchive
 * @covers Serps\Core\Url\AlterableUrlTrait
 * @covers Serps\Core\Url\UrlArchiveTrait
 */
class UrlTest extends \PHPUnit_Framework_TestCase
{


    public function testConstructor()
    {
        $url = new Url(
            'example.com',
            'somepath',
            'http',
            ['foo' => 'bar', new Url\QueryParam('baz', 'qux')],
            'somehash',
            81,
            'uname',
            'psw'
        );

        $this->assertEquals('example.com', $url->getHost());
        $this->assertEquals('somepath', $url->getPath());
        $this->assertEquals('http', $url->getScheme());
        $this->assertEquals('bar', $url->getParamValue('foo'));
        $this->assertEquals('qux', $url->getParamValue('baz'));
        $this->assertEquals('somehash', $url->getHash());
        $this->assertEquals(81, $url->getPort());
        $this->assertEquals('uname', $url->getUser());
        $this->assertEquals('psw', $url->getPassword());
        
        $this->assertEquals(
            'http://uname:psw@example.com:81/somepath?foo=bar&baz=qux#somehash',
            $url->buildUrl()
        );
    }

    public function testGetUrl()
    {
        $builder = new Url('example.com');
        $this->assertEquals('https://example.com', $builder->buildUrl());

        $builder->setHash('foo');
        $this->assertEquals('https://example.com#foo', $builder->buildUrl());

        $builder->setParam('foo', 'bar');
        $builder->setParam('foobar', 'foo bar');
        $this->assertEquals('https://example.com?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setPath('some/path');
        $this->assertEquals('https://example.com/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setScheme('http');
        $this->assertEquals('http://example.com/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        // if http and port is 80 we don't show it
        $builder->setPort(80);
        $this->assertEquals('http://example.com/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        // if https and port != 443 we show it
        $builder->setScheme('https');
        $this->assertEquals('https://example.com:80/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        // if https and port is 443 we don't show it
        $builder->setPort(443);
        $this->assertEquals('https://example.com/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setScheme('http');
        $this->assertEquals('http://example.com:443/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setUser('homer');
        $this->assertEquals('http://homer@example.com:443/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setPass('donuts');
        $this->assertEquals('http://homer:donuts@example.com:443/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setUser(null);
        $this->assertEquals('http://example.com:443/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());
    }

    public function testSetParam()
    {
        $builder = new Url('example');
        $this->assertEquals('', $builder->getQueryString());

        $builder->setParam('foo', 'bar');
        $this->assertEquals('foo=bar', $builder->getQueryString());

        $builder->setParam('foo', 'baz');
        $this->assertEquals('foo=baz', $builder->getQueryString());

        $builder->setParam('foobar', 'foo bar');
        $this->assertEquals('foo=baz&foobar=foo+bar', $builder->getQueryString());

        $builder->setParam('foobar', 'foo bar', true);
        $this->assertEquals('foo=baz&foobar=foo bar', $builder->getQueryString());
    }

    public function testGetParamValue()
    {
        $builder = new Url('example');

        $this->assertNull($builder->getParamValue('q'));
        $this->assertEquals('foo', $builder->getParamValue('q', 'foo'));

        $builder->setParam('q', 'bar');
        $this->assertEquals('bar', $builder->getParamValue('q', 'foo'));

        $builder->setParam('q', 'a+b');
        $this->assertEquals('a%2Bb', $builder->getParamValue('q'));
    }

    public function testGetParamRawValue()
    {
        $builder = new Url('example');

        $this->assertNull($builder->getParamRawValue('q'));
        $this->assertEquals('foo', $builder->getParamRawValue('q', 'foo'));

        $builder->setParam('q', 'bar');
        $this->assertEquals('bar', $builder->getParamRawValue('q', 'foo'));

        $builder->setParam('q', 'a+b');
        $this->assertEquals('a+b', $builder->getParamRawValue('q'));
    }



    public function testRemoveParam()
    {
        $builder = new Url('example');
        $this->assertEquals('', $builder->getQueryString());

        $builder->setParam('foo', 'bar');
        $builder->setParam('foobar', 'foo bar');
        $this->assertEquals('foo=bar&foobar=foo+bar', $builder->getQueryString());

        $builder->removeParam('foo');
        $this->assertEquals('foobar=foo+bar', $builder->getQueryString());
    }

    public function testSetHost()
    {
        $builder = new Url('example');
        $this->assertEquals('example', $builder->getHost());
        $builder->setHost('google.com');
        $this->assertEquals('google.com', $builder->getHost());
    }

    public function testGetParams()
    {
        $builder = new Url('example');
        $this->assertEquals([], $builder->getParams());

        $builder->setParam('foo', 'bar');
        $this->assertCount(1, $builder->getParams());
        $this->assertArrayHasKey('foo', $builder->getParams());
        $this->assertEquals('bar', $builder->getParams()['foo']->getValue());
    }

    public function testFromString()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        $this->assertInstanceOf(Url::class, $url);
        $this->assertEquals('https://foo/bar?qux=baz', $url->buildUrl());
    }

    /**
     * Params need to be raw when parsed
     */
    public function testQueryParamParsing()
    {
        $url = new UrlArchive(
            'google.com',
            '/',
            'http',
            [
                'foo' => 'foo+foo',
                new Url\QueryParam('bar', 'bar+bar', false),
                new Url\QueryParam('baz', 'baz+baz', true),
            ]
        );

        $this->assertEquals('foo%2Bfoo', $url->getParamValue('foo'));
        $this->assertEquals('foo+foo', $url->getParamRawValue('foo'));
        $this->assertEquals('bar%2Bbar', $url->getParamValue('bar'));
        $this->assertEquals('bar+bar', $url->getParamRawValue('bar'));
        $this->assertEquals('baz+baz', $url->getParamValue('baz'));
        $this->assertEquals('baz+baz', $url->getParamRawValue('baz'));
    }


    public function testResolve()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');
        $urlArchive = UrlArchive::fromString('https://foo/bar?qux=baz');

        $newUrl = $url->resolve('//bar');
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(Url::class, $newUrl);

        $newUrl = $urlArchive->resolve('//bar');
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(UrlArchive::class, $newUrl);

        $newUrl = $url->resolve('/baz');
        $this->assertEquals('https://foo/baz', $newUrl->buildUrl());

        $newUrl = $url->resolve('http://baz/foo');
        $this->assertEquals('http://baz/foo', $newUrl->buildUrl());
    }

    public function testResolveAs()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        // Resolve as other class
        $newUrl = $url->resolve('//bar', UrlArchive::class);
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(Url\UrlArchiveInterface::class, $newUrl);

        $newUrl = $url->resolve('//bar', Url::class);
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(Url\UrlArchiveInterface::class, $newUrl);

        // Resolve as string
        $newUrl = $url->resolveAsString('//bar');
        $this->assertInternalType('string', $newUrl);
        $this->assertEquals('https://bar', $newUrl);
    }

    public function testResolveAsBadType()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        $this->setExpectedException(\InvalidArgumentException::class);
        $url->resolve('//bar', []);
    }

    public function testResolveAsBadClass()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        $this->setExpectedException(\InvalidArgumentException::class);
        $url->resolve('//bar', Cookie::class);
    }
}
