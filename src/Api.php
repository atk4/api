<?php
namespace atk4\api;

class Api
{

    public $request;
    public $response;

    public $requestData;

    /**
     * Reads everything off globals
     */
    function __construct()
    {

        $this->request = \Zend\Diactoros\ServerRequestFactory::fromGlobals();
        $this->path = $this->request->getUri()->getPath();

        if (isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['REQUEST_URI'])) {
            // both script name and request uri are supplied, possibly
            // we would want to extract path relative from script location

            $path = $_SERVER['REQUEST_URI'];
            $script = $_SERVER['SCRIPT_NAME'];

            $this->path = str_replace($script, '', $path);
        }

        $ct = $this->request->getHeader('Content-Type');

        if ($ct && strtolower($ct[0])=='application/json') {
            $this->requestData = json_decode($this->request->getBody()->getContents(), true);
        } else {
            $this->requestData = $this->request->getParsedBody();
        }
    }

    function match($pattern, $arg = null) {

        $path = explode('/', rtrim($this->path, '/'));
        $pattern = explode('/', rtrim($pattern, '/'));

        $vars = [];

        $sanity = 50;
        while ($path || $pattern) {

            $p = array_shift($path);
            $r = array_shift($pattern);

            if ($p === null && $r === '') {
                continue;
            }

            // must make sure both match
            if ($p === $r) continue;

            // pattern 'r' accepts anything
            if ($r == '*' && strlen($p)) continue;

            if ($r === null || $r === '') {
                return false;
            }

            if ($r[0] == ':' && strlen($p)) {
                $vars[] = $p;
                continue;
            }

            // good until the end
            if ($r == '**' ) break;

            return false;

            if (!$sanity--) {
                throw new Exception(['Insanity while matching']);
            }
        }

        if ($arg === null) {
            return true;
        }

        try {
            $ret = call_user_func_array($arg, $vars);
        } catch(\Exception $e) {
            $this->caughtException($e);
        }

        if ($ret instanceof \atk4\data\Model) {
            $ret = $ret->export();
        }

        if ($ret !== null) {
            if (!$this->response) {
                $this->response = 
                    new \Zend\Diactoros\Response\JsonResponse(
                        $ret,
                        200,
                        [],
                        JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                    );
            }

            $emitter = new \Zend\Diactoros\Response\SapiEmitter();
            $emitter->emit($this->response);
            exit;
        }
    }

    public function get($pattern, $arg = null)
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->match($pattern, $arg);
        }
    }

    public function post($pattern, $arg = null)
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->match($pattern, $arg);
        }
    }

    public function patch($pattern, $arg = null)
    {
        if ($this->request->getMethod() === 'PATCH') {
            return $this->match($pattern, $arg);
        }
    }
    public function delete($pattern, $arg = null)
    {
        if ($this->request->getMethod() === 'DELETE') {
            return $this->match($pattern, $arg);
        }
    }

    public function rest($pattern, $model = null)
    {
        $this->get($pattern, function() use($model) {
            return $model;
        });

        $this->get($pattern.'/:id', function($id) use($model) {
            return $model->load($id)->get();
        });

        $this->patch($pattern.'/:id', function($id) use($model) {
            return $model->load($id)->set($this->requestData)->save()->get();
        });
        $this->post($pattern.'/:id', function($id) use($model) {
            return $model->load($id)->set($this->requestData)->save()->get();
        });
        $this->delete($pattern.'/:id', function($id) use($model) {
            return !$model->load($id)->delete()->loaded();
        });

        $this->post($pattern.'/', function() use($model) {
            return $model->set($this->requestData)->save()->get();
        });
    }

    function caughtException(\Exception $e)
    {
        $params = [];
        foreach ($e->getParams() as $key => $val) {
            $params[$key]=$e->toString($val);
        }

        $this->response = 
            new \Zend\Diactoros\Response\JsonResponse(
                [
                    'error'=>[
                        'message'=>$e->getMessage(), 
                        'args'=>$params,
                    ]
                ],
                $e->getCode() ?: 500,
                [],
                JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
            );

        $emitter = new \Zend\Diactoros\Response\SapiEmitter();
        $emitter->emit($this->response);
        exit;
    }
}
