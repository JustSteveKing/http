<?php

namespace Utopia\Http;

use PHPUnit\Framework\TestCase;
use Utopia\DI\Container;
use Utopia\DI\Dependency;
use Utopia\Http\Tests\UtopiaFPMRequestTest;
use Utopia\Http\Validator\Text;
use Utopia\Http\Adapter\FPM\Request;
use Utopia\Http\Adapter\FPM\Response;
use Utopia\Http\Adapter\FPM\Server;

class HttpTest extends TestCase
{
    protected ?Http $http;

    protected Container $context;

    protected ?string $method;

    protected ?string $uri;

    public function setUp(): void
    {
        Http::reset();
        $this->context = new Container();
        $this->http = new Http(new Server(), $this->context, 'Asia/Tel_Aviv');
        $this->saveRequest();
    }

    public function tearDown(): void
    {
        $this->http = null;
        $this->restoreRequest();
    }

    protected function saveRequest(): void
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->uri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function restoreRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = $this->method;
        $_SERVER['REQUEST_URI'] = $this->uri;
    }

    public function testCanGetDifferentModes(): void
    {
        $this->assertEmpty(Http::getMode());
        $this->assertFalse(Http::isProduction());
        $this->assertFalse(Http::isDevelopment());
        $this->assertFalse(Http::isStage());

        Http::setMode(Http::MODE_TYPE_PRODUCTION);

        $this->assertEquals(Http::MODE_TYPE_PRODUCTION, Http::getMode());
        $this->assertTrue(Http::isProduction());
        $this->assertFalse(Http::isDevelopment());
        $this->assertFalse(Http::isStage());

        Http::setMode(Http::MODE_TYPE_DEVELOPMENT);

        $this->assertEquals(Http::MODE_TYPE_DEVELOPMENT, Http::getMode());
        $this->assertFalse(Http::isProduction());
        $this->assertTrue(Http::isDevelopment());
        $this->assertFalse(Http::isStage());

        Http::setMode(Http::MODE_TYPE_STAGE);

        $this->assertEquals(Http::MODE_TYPE_STAGE, Http::getMode());
        $this->assertFalse(Http::isProduction());
        $this->assertFalse(Http::isDevelopment());
        $this->assertTrue(Http::isStage());
    }

    public function testCanExecuteRoute(): void
    {
        $this->http
            ->error()
            ->dependency('error')
            ->action(function ($error) {
                echo 'error: ' . $error->getMessage();
            });

        // Default Params
        $route = new Route('GET', '/path');

        $route
            ->param('x', 'x-def', new Text(200), 'x param', true)
            ->param('y', 'y-def', new Text(200), 'y param', true)
            ->action(function ($x, $y) {
                echo $x . '-' . $y;
            });

        \ob_start();
        $this->http->execute($route, new Request(), new Container());
        $result = \ob_get_contents();
        \ob_end_clean();

        // With Params
        $route = new Route('GET', '/path');

        $route
            ->param('x', 'x-def', new Text(200), 'x param', true)
            ->param('y', 'y-def', new Text(200), 'y param', true)
            ->dependency('rand')
            ->param('z', 'z-def', function ($rand) {
                echo $rand . '-';

                return new Text(200);
            }, 'z param', true, ['rand'])
            ->action(function ($x, $y, $z, $rand) {
                echo $x . '-', $y;
            });

        \ob_start();
        $request = new UtopiaFPMRequestTest();
        $request::_setParams(['x' => 'param-x', 'y' => 'param-y', 'z' => 'param-z']);
        $this->http->execute($route, $request, $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        // $this->assertEquals($resource . '-param-x-param-y', $result);

        // With Error
        $route = new Route('GET', '/path');

        $route
            ->param('x', 'x-def', new Text(1, min: 0), 'x param', false)
            ->param('y', 'y-def', new Text(1, min: 0), 'y param', false)
            ->action(function ($x, $y) {
                echo $x . '-', $y;
            });

        \ob_start();
        $request = new UtopiaFPMRequestTest();
        $request::_setParams(['x' => 'param-x', 'y' => 'param-y']);
        $this->http->execute($route, $request, $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('error: Invalid `x` param: Value must be a valid string and no longer than 1 chars', $result);

        // With Hooks
        $this->http
            ->init()
            ->dependency('rand')
            ->action(function ($rand) {
                echo 'init-' . $rand . '-';
            });

        $this->http
            ->shutdown()
            ->action(function () {
                echo '-shutdown';
            });

        $this->http
            ->init()
            ->groups(['api'])
            ->action(function () {
                echo '(init-api)-';
            });

        $this->http
            ->shutdown()
            ->groups(['api'])
            ->action(function () {
                echo '-(shutdown-api)';
            });

        $this->http
            ->init()
            ->groups(['homepage'])
            ->action(function () {
                echo '(init-homepage)-';
            });

        $this->http
            ->shutdown()
            ->groups(['homepage'])
            ->action(function () {
                echo '-(shutdown-homepage)';
            });

        $route = new Route('GET', '/path');

        $route
            ->groups(['api'])
            ->param('x', 'x-def', new Text(200), 'x param', false)
            ->param('y', 'y-def', new Text(200), 'y param', false)
            ->action(function ($x, $y) {
                echo $x . '-', $y;
            });

        $homepage = new Route('GET', '/path');

        $homepage
            ->groups(['homepage'])
            ->param('x', 'x-def', new Text(200), 'x param', false)
            ->param('y', 'y-def', new Text(200), 'y param', false)
            ->action(function ($x, $y) {
                echo $x . '*', $y;
            });

        \ob_start();
        $request = new UtopiaFPMRequestTest();
        $request::_setParams(['x' => 'param-x', 'y' => 'param-y']);
        $this->http->execute($route, $request, $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('init-' . $resource . '-(init-api)-param-x-param-y-(shutdown-api)-shutdown', $result);

        $resource = $this->http->getResource('rand', $this->context);
        \ob_start();
        $request = new UtopiaFPMRequestTest();
        $request::_setParams(['x' => 'param-x', 'y' => 'param-y']);
        $this->http->execute($homepage, $request, $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('init-' . $resource . '-(init-homepage)-param-x*param-y-(shutdown-homepage)-shutdown', $result);
    }

    public function testCanAddAndExecuteHooks()
    {
        $this->http
            ->init()
            ->action(function () {
                echo '(init)-';
            });

        $this->http
            ->shutdown()
            ->action(function () {
                echo '-(shutdown)';
            });

        // Default Params
        $route = new Route('GET', '/path');
        $route
            ->param('x', 'x-def', new Text(200), 'x param', true)
            ->action(function ($x) {
                echo $x;
            });

        \ob_start();
        $this->http->execute($route, new Request(), $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('(init)-x-def-(shutdown)', $result);

        // Default Params
        $route = new Route('GET', '/path');
        $route
            ->param('x', 'x-def', new Text(200), 'x param', true)
            ->hook(false)
            ->action(function ($x) {
                echo $x;
            });

        \ob_start();
        $this->http->execute($route, new Request(), $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('x-def', $result);
    }

    public function testAllowRouteOverrides()
    {
        Http::setAllowOverride(false);
        $this->assertFalse(Http::getAllowOverride());
        Http::get('/')->action(function () {
            echo 'Hello first';
        });

        try {
            Http::get('/')->action(function () {
                echo 'Hello second';
            });
            $this->fail('Failed to throw exception');
        } catch (\Exception $e) {
            // Threw exception as expected
            $this->assertEquals('Route for (GET:) already registered.', $e->getMessage());
        }

        // Test success
        Http::setAllowOverride(true);
        $this->assertTrue(Http::getAllowOverride());
        Http::get('/')->action(function () {
            echo 'Hello first';
        });

        Http::get('/')->action(function () {
            echo 'Hello second';
        });
    }

    public function testCanHookThrowExceptions()
    {
        $this->http
            ->init()
            ->param('y', '', new Text(5), 'y param', false)
            ->action(function ($y) {
                echo '(init)-' . $y . '-';
            });

        $this->http
            ->error()
            ->dependency('error')
            ->action(function ($error) {
                echo 'error-' . $error->getMessage();
            });

        $this->http
            ->shutdown()
            ->action(function () {
                echo '-(shutdown)';
            });

        // param not provided for init
        $route = new Route('GET', '/path');
        $route
            ->param('x', 'x-def', new Text(200), 'x param', true)
            ->action(function ($x) {
                echo $x;
            });

        \ob_start();
        $this->http->execute($route, new Request(), $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('error-Param "y" is not optional.', $result);

        \ob_start();
        $_GET['y'] = 'y-def';
        $this->http->execute($route, new Request(), $this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('(init)-y-def-x-def-(shutdown)', $result);
    }

    public function testCanSetRoute()
    {
        $route = new Dependency();
        $route
            ->setName('route')
            ->setCallback(fn () => null)
        ;
        
        $this->http->getContainer()->set($route);

        $this->assertEquals($this->http->getContainer()->get('route'), null);

        $og = new Route('GET', '/path');
        $route = new Dependency();
        $route
            ->setName('route')
            ->setCallback(fn () => $og)
        ;

        $this->http->getContainer()->set($route);

        $this->assertEquals($this->http->getContainer()->get('route'), $og);
    }

    public function providerRouteMatching(): array
    {
        return [
            'GET request' => [Http::REQUEST_METHOD_GET, '/path1'],
            'GET request on different route' => [Http::REQUEST_METHOD_GET, '/path2'],
            'GET request with trailing slash #1' => [Http::REQUEST_METHOD_GET, '/path3', '/path3/'],
            'GET request with trailing slash #2' => [Http::REQUEST_METHOD_GET, '/path3/', '/path3/'],
            'GET request with trailing slash #3' => [Http::REQUEST_METHOD_GET, '/path3/', '/path3'],
            'POST request' => [Http::REQUEST_METHOD_POST, '/path1'],
            'PUT request' => [Http::REQUEST_METHOD_PUT, '/path1'],
            'PATCH request' => [Http::REQUEST_METHOD_PATCH, '/path1'],
            'DELETE request' => [Http::REQUEST_METHOD_DELETE, '/path1'],
            '1 separators' => [Http::REQUEST_METHOD_GET, '/a/'],
            '2 separators' => [Http::REQUEST_METHOD_GET, '/a/b'],
            '3 separators' => [Http::REQUEST_METHOD_GET, '/a/b/c']
        ];
    }

    /**
     * @dataProvider providerRouteMatching
     */
    public function testCanMatchRoute(string $method, string $path, string $url = null): void
    {
        $url ??= $path;
        $expected = null;

        switch ($method) {
            case Http::REQUEST_METHOD_GET:
                $expected = Http::get($path);
                break;
            case Http::REQUEST_METHOD_POST:
                $expected = Http::post($path);
                break;
            case Http::REQUEST_METHOD_PUT:
                $expected = Http::put($path);
                break;
            case Http::REQUEST_METHOD_PATCH:
                $expected = Http::patch($path);
                break;
            case Http::REQUEST_METHOD_DELETE:
                $expected = Http::delete($path);
                break;
        }

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $url;

        $route = $this->http->match(new Request());
        $this->assertEquals($expected, $route);
    }

    public function testNoMismatchRoute(): void
    {
        $requests = [
            [
                'path' => '/d/:id',
                'url' => '/d/'
            ],
            [
                'path' => '/d/:id/e/:id2',
                'url' => '/d/123/e/'
            ],
            [
                'path' => '/d/:id/e/:id2/f/:id3',
                'url' => '/d/123/e/456/f/'
            ],
        ];

        foreach ($requests as $request) {
            Http::get($request['path']);

var_dump($request['url']);
            $_SERVER['REQUEST_METHOD'] = Http::REQUEST_METHOD_GET;
            $_SERVER['REQUEST_URI'] = $request['url'];
            
            $context = new Container();

            $request = new Dependency();
            $request
                ->setName('request')
                ->setCallback(fn () => new Request());
            
            $response = new Dependency();
            $response
                ->setName('response')
                ->setCallback(fn () => new Response());

            $context
                ->set($request)
                ->set($response)
            ;

            $this->http->run($context);

            $this->assertEquals(null, $context->get('route'));
        }
    }

    public function testCanRunRequest(): void
    {
        // Test head requests

        $method = (isset($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : null;
        $uri = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : null;

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/path';

        Http::get('/path')
            ->dependency('response')
            ->action(function ($response) {
                $response->send('HELLO');
            });

        \ob_start();
        $this->http->run($this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        $this->assertStringNotContainsString('HELLO', $result);
    }

    public function testWildcardRoute(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/unknown_path';

        Http::init()
            ->action(function () {
                $route = $this->http->getRoute();
                Http::setResource('myRoute', fn () => $route);
            });


        Http::wildcard()
            ->dependency('myRoute')
            ->dependency('response')
            ->action(function (mixed $myRoute, $response) {
                if ($myRoute == null) {
                    $response->send('ROUTE IS NULL!');
                } else {
                    $response->send('HELLO');
                }
            });

        \ob_start();
        @$this->http->run($this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('HELLO', $result);

        \ob_start();
        $req = new Request();
        $req = $req->setMethod('OPTIONS');
        @$this->http->run($this->context);
        $result = \ob_get_contents();
        \ob_end_clean();

        $this->assertEquals('', $result);

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
    }
}
