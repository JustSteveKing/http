<?php

namespace Utopia;

use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function tearDown(): void
    {
        Router::reset();
    }

    public function testCanMatchUrl(): void
    {
        $routeIndex = new Route(App::REQUEST_METHOD_GET, '/');
        $routeAbout = new Route(App::REQUEST_METHOD_GET, '/about');
        $routeAboutMe = new Route(App::REQUEST_METHOD_GET, '/about/me');

        Router::addRoute($routeIndex);
        Router::addRoute($routeAbout);
        Router::addRoute($routeAboutMe);

        $this->assertEquals($routeIndex, Router::match(App::REQUEST_METHOD_GET, '/'));
        $this->assertEquals($routeAbout, Router::match(App::REQUEST_METHOD_GET, '/about'));
        $this->assertEquals($routeAboutMe, Router::match(App::REQUEST_METHOD_GET, '/about/me'));
    }

    public function testCanMatchUrlWithPlaceholder(): void
    {
        $routeBlog = new Route(App::REQUEST_METHOD_GET, '/blog');
        $routeBlogAuthors = new Route(App::REQUEST_METHOD_GET, '/blog/authors');
        $routeBlogAuthorsComments = new Route(App::REQUEST_METHOD_GET, '/blog/authors/comments');
        $routeBlogPost = new Route(App::REQUEST_METHOD_GET, '/blog/:post');
        $routeBlogPostComments = new Route(App::REQUEST_METHOD_GET, '/blog/:post/comments');
        $routeBlogPostCommentsSingle = new Route(App::REQUEST_METHOD_GET, '/blog/:post/comments/:comment');

        Router::addRoute($routeBlog);
        Router::addRoute($routeBlogAuthors);
        Router::addRoute($routeBlogAuthorsComments);
        Router::addRoute($routeBlogPost);
        Router::addRoute($routeBlogPostComments);
        Router::addRoute($routeBlogPostCommentsSingle);

        $this->assertEquals($routeBlog, Router::match(App::REQUEST_METHOD_GET, '/blog'));
        $this->assertEquals($routeBlogAuthors, Router::match(App::REQUEST_METHOD_GET, '/blog/authors'));
        $this->assertEquals($routeBlogAuthorsComments, Router::match(App::REQUEST_METHOD_GET, '/blog/authors/comments'));
        $this->assertEquals($routeBlogPost, Router::match(App::REQUEST_METHOD_GET, '/blog/:post'));
        $this->assertEquals($routeBlogPostComments, Router::match(App::REQUEST_METHOD_GET, '/blog/:post/comments'));
        $this->assertEquals($routeBlogPostCommentsSingle, Router::match(App::REQUEST_METHOD_GET, '/blog/:post/comments/:comment'));
    }

    public function testCanMatchHttpMethod(): void
    {
        $routeGET = new Route(App::REQUEST_METHOD_GET, '/');
        $routePOST = new Route(App::REQUEST_METHOD_POST, '/');

        Router::addRoute($routeGET);
        Router::addRoute($routePOST);

        $this->assertEquals($routeGET, Router::match(App::REQUEST_METHOD_GET, '/'));
        $this->assertEquals($routePOST, Router::match(App::REQUEST_METHOD_POST, '/'));

        $this->assertNotEquals($routeGET, Router::match(App::REQUEST_METHOD_POST, '/'));
        $this->assertNotEquals($routePOST, Router::match(App::REQUEST_METHOD_GET, '/'));
    }

    public function testCanMatchAlias(): void
    {
        $routeGET = new Route(App::REQUEST_METHOD_GET, '/target');
        $routeGET->alias('/alias')->alias('/alias2');

        Router::addRoute($routeGET);

        $this->assertEquals($routeGET, Router::match(App::REQUEST_METHOD_GET, '/target'));
        $this->assertEquals($routeGET, Router::match(App::REQUEST_METHOD_GET, '/alias'));
        $this->assertEquals($routeGET, Router::match(App::REQUEST_METHOD_GET, '/alias2'));
    }

    public function testCannotFindUnknownRouteByPath(): void
    {
        $this->assertNull(Router::match(App::REQUEST_METHOD_GET, '/404'));
    }

    public function testCannotFindUnknownRouteByMethod(): void
    {
        $route = new Route(App::REQUEST_METHOD_GET, '/404');

        Router::addRoute($route);

        $this->assertEquals($route, Router::match(App::REQUEST_METHOD_GET, '/404'));

        $this->assertNull(Router::match(App::REQUEST_METHOD_POST, '/404'));
    }
}
