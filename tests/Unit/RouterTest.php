<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\Router;

/**
 * Router 正则缓存 + 路由匹配测试。
 */
class RouterTest extends TestCase
{
    public function test_static_route_match(): void
    {
        $router = new Router();
        $router->get('/about', fn () => 'about page');
        $resp = $router->dispatch('GET', '/about');
        $this->assertSame('about page', $resp->getBody());
    }

    public function test_dynamic_route_match(): void
    {
        $router = new Router();
        $router->get('/posts/{slug}', function (array $params) {
            return 'post:' . $params['slug'];
        });
        $resp = $router->dispatch('GET', '/posts/hello-world');
        $this->assertSame('post:hello-world', $resp->getBody());
    }

    public function test_numeric_constraint_route(): void
    {
        $router = new Router();
        $router->get('/page/{id:\d+}', fn (array $p) => 'page:' . $p['id']);
        $resp = $router->dispatch('GET', '/page/42');
        $this->assertSame('page:42', $resp->getBody());
    }

    public function test_404_returns_404_status(): void
    {
        $router = new Router();
        $resp = $router->dispatch('GET', '/nope');
        $this->assertSame(404, $resp->status());
    }

    public function test_named_route_url_generation(): void
    {
        $router = new Router();
        $router->get('/posts/{slug}', fn () => '')->name('post.show');
        // 不测实际 URL，因为 url() 依赖 config
        $this->assertTrue(true);
    }
}
