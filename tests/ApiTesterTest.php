<?php

namespace atk4\api\tests;

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
    }
}
