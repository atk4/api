<?php

declare(strict_types=1);

namespace Atk4\Api\Tests;

class ApiTesterTest extends ApiTester
{
    public function testRequest()
    {
        $this->assertApi(
            'pong',
            function ($api) {
                $api->get('/ping', function () {
                    return 'pong';
                });
                $api->get('/ping', function () {
                    return 'bad-pong';
                });
            },
            '/ping',
            'GET'
        );

        $this->assertReq('pong', function () {
            return 'pong';
        });
        $this->assertReq('pong', function () {
            return 'pong';
        }, 'POST');

        $this->assertReq('pong', function () {
            return 'pong';
        }, 'PATCH');

        $this->assertReq('pong', function () {
            return 'pong';
        }, 'PUT');

        $this->assertReq('pong', function () {
            return 'pong';
        }, 'DELETE');
    }
}
