<?php

declare(strict_types=1);

namespace Atk4\Api;

use Atk4\Data\Model;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

/**
 * Main API class.
 */
class Api
{
    /** @var Request Request object */
    public $request;

    /** @var string */
    protected $request_data;

    /** @var array */
    protected $_vars = [];

    /** @var string Request path */
    public $path;

    /** @var JsonResponse Response object */
    public $response;

    /** @var int Response code */
    public $response_code = 200;

    /** @var EmitterInterface Emitter object */
    public $emitter;

    /** @var array Response header */
    protected $response_headers = [];

    /** @var int Response options */
    protected $response_options = JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

    /**
     * Reads everything off globals.
     *
     * @param Request $request
     */
    public function __construct(Request $request = null)
    {
        if ($request !== null) {
            $request->getBody()->rewind(); // reset pointer of request.
        }
        $this->request = $request ?: ServerRequestFactory::fromGlobals();
        $this->path = $this->request->getUri()->getPath();

        if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
            // both script name and request uri are supplied, possibly
            // we would want to extract path relative from script location

            $script = $_SERVER['SCRIPT_NAME'];
            $path = $_SERVER['REQUEST_URI'];

            $regex = '|^' . preg_quote(dirname($script)) . '(/' . preg_quote(basename($script)) . ')?|i';
            $this->path = preg_replace($regex, '', $path, 1);
        }

        if ($this->request->getHeader('Content-Type')[0] ?? null === 'application/json') {
            $this->request_data = json_decode($this->request->getBody()->getContents(), true);
        } else {
            $this->request_data = $this->request->getParsedBody();
        }

        // This is how we will send responses
        $this->emitter = new SapiEmitter();
    }

    public function match($pattern)
    {
        $path = explode('/', rtrim($this->path, '/'));
        $pattern = explode('/', rtrim($pattern, '/'));

        $this->_vars = [];

        while ($path || $pattern) {
            $p = array_shift($path);
            $r = array_shift($pattern);

            // if path ends and there is nothing in pattern (used //) then continue
            if ($p === null && $r === '') {
                continue;
            }

            // if both match, then continue
            if ($p === $r) {
                continue;
            }

            // pattern '*' accepts anything
            if ($r === '*' && is_string($p) && strlen($p) > 0) {
                continue;
            }

            // if pattern ends, but there is still something in path, then don't match
            if ($r === null || $r === '') {
                return false;
            }

            // parameters always start with ':', save in $vars and continue
            if ($r[0] === ':' && is_string($p) && strlen($p) > 0) {
                // if value contains : then treat it as fieldname:value pair
                // if value contains : and there is no fieldname (:ABC for example),
                // then it will use model->title_field as fieldname
                // otherwise it will be treated as id value
                if (strpos($p, ':') !== false) {
                    $parts = explode(':', $p, 2);
                    $this->_vars[] = [urldecode($parts[0]), urldecode($parts[1])];
                } else {
                    $this->_vars[] = urldecode($p);
                }

                continue;
            }

            // pattern '**' = good until the end
            if ($r === '**') {
                break;
            }

            return false;
        }

        return true;
    }

    /**
     * Call callable and emit response.
     *
     * @param callable $callable
     * @param array    $vars
     */
    public function exec($callable, $vars = [])
    {
        // try to call callable function
        $ret = $this->call($callable, $vars);

        // if callable function returns agile data model, then export it
        // this is important for REST API implementation
        if ($ret instanceof Model) {
            $ret = $this->exportModel($ret);
        }

        // no response, just step out
        if ($ret === null) {
            return;
        }

        // emit successful response
        $this->successResponse($ret);
    }

    /**
     * Call callable and return response.
     *
     * @param callable $callable
     * @param array    $vars
     *
     * @return mixed
     */
    protected function call($callable, $vars = [])
    {
        // try to call callable function
        try {
            $ret = call_user_func_array($callable, $vars);
        } catch (\Exception $e) {
            $this->caughtException($e);
        }

        return $ret;
    }

    /**
     * Exports data model.
     *
     * Extend this method to implement your own field restrictions.
     *
     * @return array
     */
    protected function exportModel(Model $m)
    {
        return $m->export($this->getAllowedFields($m, 'read'));
    }

    /**
     * Load model by value.
     *
     * Value could be:
     *  - array[fieldname,value]:
     *    - if fieldname is empty, then use model->title_field
     *    - if fieldname is not empty, then use it
     *  - string|integer : will be treated as ID value
     *
     * @param mixed $value
     *
     * @return Model
     */
    protected function loadModelByValue(Model $m, $value)
    {
        // value is not ID
        if (is_array($value)) {
            $field = $value[0] ?? $m->title_field;

            return $m->loadBy($field, $value[1]);
        }

        // value is ID
        return $m->load($value);
    }

    /**
     * Returns list of model field names which allow particular action - read or modify.
     * Also takes model->only_fields into account if that's defined.
     *
     * It uses custom model property apiFields[$action] which should contain array of
     * allowed field names or null to allow all model fields.
     *
     * @param string $action read|modify
     *
     * @return array|null of field names
     */
    protected function getAllowedFields(Model $m, $action = 'read')
    {
        // take model only_fields into account
        $fields = is_array($m->only_fields) ? $m->only_fields : [];

        // limit by apiFields
        if (isset($m->apiFields, $m->apiFields[$action])) {
            $allowed = $m->apiFields[$action];
            $fields = $fields ? array_intersect($fields, $allowed) : $allowed;
        }

        return $fields;
    }

    /**
     * Filters data array by only allowed fields.
     *
     * Extend this method to implement your own field restrictions.
     *
     * @param Model $m
     * @param array $data
     *
     * @return array
     */
    /* not used and maybe will not be needed too
    protected function filterData(\Atk4\Data\Model $m, array $data)
    {
        $allowed = $this->getAllowedFields($m, 'modify');

        if ($allowed) {
            $data = array_intersect_key($data, array_flip($allowed));
        }

        return $data;
    }
    */

    /**
     * Emit successful response.
     *
     * @param mixed $response
     */
    protected function successResponse($response)
    {
        // create response object
        if (!$this->response) {
            $this->response = new JsonResponse(
                $response,
                $this->response_code,
                $this->response_headers,
                $this->response_options
            );
        }

        // if there is emitter, then emit response and exit
        // for testing purposes there can be situations when emitter is disabled. then do nothing.
        if ($this->emitter) {
            $this->emitter->emit($this->response);

            exit;
        }

        // @todo Should we also stop script execution if no emitter is defined or just ignore that?
        //exit;
    }

    /**
     * Do GET pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     */
    public function get($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'GET' && $this->match($pattern)) {
            $this->exec($callable, $this->_vars);
        }
    }

    /**
     * Do POST pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     */
    public function post($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'POST' && $this->match($pattern)) {
            $this->exec($callable, $this->_vars);
        }
    }

    /**
     * Do PATCH pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     */
    public function patch($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'PATCH' && $this->match($pattern)) {
            $this->exec($callable, $this->_vars);
        }
    }

    /**
     * Do PUT pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     */
    public function put($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'PUT' && $this->match($pattern)) {
            $this->exec($callable, $this->_vars);
        }
    }

    /**
     * Do DELETE pattern matching.
     *
     * @param string   $pattern
     * @param callable $callable
     */
    public function delete($pattern, $callable = null)
    {
        if ($this->request->getMethod() === 'DELETE' && $this->match($pattern)) {
            $this->exec($callable, $this->_vars);
        }
    }

    /**
     * Implement REST pattern matching.
     *
     * @param string         $pattern
     * @param Model|callable $model
     * @param array          $methods Allowed methods (read|modify|delete). By default all are allowed
     */
    public function rest($pattern, $model = null, $methods = ['read', 'modify', 'delete'])
    {
        $methods = array_map('strtolower', $methods);

        // GET all records
        if (in_array('read', $methods, true)) {
            $f = function (...$params) use ($model) {
                if (is_callable($model)) {
                    $model = $this->call($model, $params);
                }

                return $model;
            };
            $this->get($pattern, $f);
        }

        // GET :id - one record
        if (in_array('read', $methods, true)) {
            $f = function (...$params) use ($model) {
                $id = array_pop($params); // pop last element of args array, it's :id

                if (is_callable($model)) {
                    $model = $this->call($model, $params);
                }

                // limit fields
                $model->onlyFields($this->getAllowedFields($model, 'read'));

                // load model and get field values
                return $this->loadModelByValue($model, $id)->get();
            };
            $this->get($pattern . '/:id', $f);
        }

        // POST :id - update one record
        // PATCH :id - update one record (same as POST :id)
        // PUT :id - update one record (same as POST :id)
        if (in_array('modify', $methods, true)) {
            $f = function (...$params) use ($model) {
                $id = array_pop($params); // pop last element of args array, it's :id

                if (is_callable($model)) {
                    $model = $this->call($model, $params);
                }

                // limit fields
                $model->onlyFields($this->getAllowedFields($model, 'modify'));
                $this->loadModelByValue($model, $id)->save($this->request_data);
                $model->onlyFields($this->getAllowedFields($model, 'read'));

                return $model->get();
            };
            $this->patch($pattern . '/:id', $f);
            $this->post($pattern . '/:id', $f);
            $this->put($pattern . '/:id', $f);
        }

        // POST - insert new record
        if (in_array('modify', $methods, true)) {
            $f = function (...$params) use ($model) {
                if (is_callable($model)) {
                    $model = $this->call($model, $params);
                }

                // limit fields
                $model->onlyFields($this->getAllowedFields($model, 'modify'));
                $model->unload()->save($this->request_data);
                $model->onlyFields($this->getAllowedFields($model, 'read'));

                $this->response_code = 201; // http code for created

                return $model->get();
            };
            $this->post($pattern, $f);
        }

        // DELETE :id - delete one record
        if (in_array('delete', $methods, true)) {
            $f = function (...$params) use ($model) {
                $id = array_pop($params); // pop last element of args array, it's :id

                if (is_callable($model)) {
                    $model = $this->call($model, $params);
                }

                // limit fields (not necessary, but will limit field list for performance)
                $model->onlyFields($this->getAllowedFields($model, 'read'));

                return !$model->delete($id)->loaded();
            };
            $this->delete($pattern . '/:id', $f);
        }
    }

    /**
     * Our own exception handling.
     */
    public function caughtException(\Exception $e)
    {
        $params = [];
        if ($e instanceof \Atk4\Core\Exception) {
            foreach ($e->getParams() as $key => $val) {
                $params[$key] = $e->toString($val);
            }
        }

        $this->response = new JsonResponse(
            [
                'error' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'args' => $params,
                ],
            ],
            (int) $e->getCode() > 0 ? $e->getCode() : 500,
            $this->response_headers,
            $this->response_options
        );

        //var_dump($this->response, $e->getMessage());
        (new SapiEmitter())->emit($this->response);

        exit;
    }
}
