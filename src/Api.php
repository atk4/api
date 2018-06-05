<?php

namespace atk4\api;

/**
 * Main API class.
 */
class Api
{
    /** @var \Zend\Diactoros\ServerRequest Request object */
    public $request;

    /** @var \Zend\Diactoros\Response\JsonResponse Response object */
    public $response;

    /** @var \Zend\Diactoros\Response\SapiEmitter Emitter object */
    public $emitter;

    /** @var string */
    protected $requestData;

    /** @var string Request path */
    public $path;

    /**
     * Reads everything off globals.
     *
     * @param \Zend\Diactoros\ServerRequest $request
     */
    public function __construct($request = null)
    {
        $this->request = $request ?: \Zend\Diactoros\ServerRequestFactory::fromGlobals();
        $this->path = $this->request->getUri()->getPath();

        if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
            // both script name and request uri are supplied, possibly
            // we would want to extract path relative from script location

            $script = $_SERVER['SCRIPT_NAME'];
            $path = $_SERVER['REQUEST_URI'];

            $this->path = str_replace($script, '', $path);
        }

        $ct = $this->request->getHeader('Content-Type');

        if ($ct && strtolower($ct[0]) == 'application/json') {
            $this->requestData = json_decode($this->request->getBody()->getContents(), true);
        } else {
            $this->requestData = $this->request->getParsedBody();
        }

        // This is how we will send responses
        $this->emitter = new \Zend\Diactoros\Response\SapiEmitter();
    }

    /**
     * Do pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return mixed
     */
    public function match($pattern, $callable = null)
    {
        $path = explode('/', rtrim($this->path, '/'));
        $pattern = explode('/', rtrim($pattern, '/'));

        $vars = [];

        while ($path || $pattern) {
            $p = array_shift($path);
            $r = array_shift($pattern);

            if ($p === null && $r === '') {
                continue;
            }

            // must make sure both match
            if ($p === $r) {
                continue;
            }

            // pattern 'r' accepts anything
            if ($r == '*' && strlen($p)) {
                continue;
            }

            if ($r === null || $r === '') {
                return false;
            }

            if ($r[0] == ':' && strlen($p)) {
                $vars[] = $p;
                continue;
            }

            // good until the end
            if ($r == '**') {
                break;
            }

            return false;
        }

        // if no callable function set - just say that it matches
        if ($callable === null) {
            return true;
        }

        // try to call callable function
        try {
            $ret = call_user_func_array($callable, $vars);
        } catch (\Exception $e) {
            $this->caughtException($e);
        }

        // if callable function returns agile data model, then export it
        // this is important for REST API implementation
        if ($ret instanceof \atk4\data\Model) {
            $ret = $ret->export();
        }

        // create response object
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

            // emit response and exit
            $this->emitter->emit($this->response);
            exit;
        }

        // @todo Should we also stop script execution if no response is received or just ignore that?
        //exit;
    }

    /**
     * Do GET pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return mixed
     */
    public function get($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->match($pattern, $callable);
        }
    }

    /**
     * Do POST pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return mixed
     */
    public function post($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->match($pattern, $callable);
        }
    }

    /**
     * Do PATCH pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return mixed
     */
    public function patch($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'PATCH') {
            return $this->match($pattern, $callable);
        }
    }

    /**
     * Do DELETE pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return mixed
     */
    public function delete($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'DELETE') {
            return $this->match($pattern, $callable);
        }
    }

    /**
     * Implement REST pattern matching.
     *
     * @param string           $pattern
     * @param \atk4\data\Model $model
     *
     * @return mixed
     */
    public function rest($pattern, $model = null)
    {
        $this->get($pattern, function () use ($model) {
            return $model;
        });

        $this->get($pattern.'/:id', function ($id) use ($model) {
            return $model->load($id)->get();
        });

        $this->patch($pattern.'/:id', function ($id) use ($model) {
            return $model->load($id)->set($this->requestData)->save()->get();
        });
        $this->post($pattern.'/:id', function ($id) use ($model) {
            return $model->load($id)->set($this->requestData)->save()->get();
        });
        $this->delete($pattern.'/:id', function ($id) use ($model) {
            return !$model->load($id)->delete()->loaded();
        });

        $this->post($pattern, function () use ($model) {
            return $model->set($this->requestData)->save()->get();
        });
    }

    /**
     * Our own exception handling.
     *
     * @param \Exception $e
     */
    public function caughtException(\Exception $e)
    {
        $params = [];
        foreach ($e->getParams() as $key => $val) {
            $params[$key] = $e->toString($val);
        }

        $this->response =
            new \Zend\Diactoros\Response\JsonResponse(
                [
                    'error'=> [
                        'message'=> $e->getMessage(),
                        'args'   => $params,
                    ],
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
