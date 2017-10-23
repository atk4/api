# Agile API Framework

[![Build Status](https://travis-ci.org/atk4/api.png?branch=develop)](https://travis-ci.org/atk4/api)
[![StyleCI](https://styleci.io/repos/107142772/shield)](https://styleci.io/repos/107142772)
[![codecov](https://codecov.io/gh/atk4/api/branch/develop/graph/badge.svg)](https://codecov.io/gh/atk4/api)
[![Code Climate](https://codeclimate.com/github/atk4/api/badges/gpa.svg)](https://codeclimate.com/github/atk4/api)
[![Issue Count](https://codeclimate.com/github/atk4/api/badges/issue_count.svg)](https://codeclimate.com/github/atk4/api)

[![License](https://poser.pugx.org/atk4/api/license)](https://packagist.org/packages/atk4/api)
[![GitHub release](https://img.shields.io/github/release/atk4/api.svg?maxAge=2592000)](https://packagist.org/packages/atk4/api)


End-to-end implementation for your REST API. Provides a very simple means for you to define API end-points for the application that already uses [Agile Data](https://github.com/atk4/data).

## Planned Features

Agile API is in development but the following features are planned:

-   [x] Simple to use.


-   [x] Model routing. Provide end-points by associating them with models.
-   [ ] Global authentication. Provide authentication strategy for entire framework.
-   [ ] Support for rate limits. Per-account, per-IP counters which can be stored in MemCache or Redis.
-   [ ] Deep logging, integrated with data persistence. Not only stores the request, but what data was affected inside persistence.
-   [ ] Support for API UNDO. Neutralize effect of API call had on your backend.

### Simple to use

To set up your API, simply create new RestAPI class instance and define routes. You can enable versioning by creating "v1" folder and placing `index.php` in that folder. Some things work and we do not want to re-invent them!

``` php
require 'vendor/autoload.php';
$app = new \atk4\api\Api();

$db = \atk4\data\Persistence::connect($DSN);

// Lets set our index page
$app->get('/', function() {
    return 'This worked!';
});

// Getting access to POST data
$app->post('/stats/:id', function($id, $data) {
   return ['Received POST', 'id'=>$id, 'post_data'=>$data] 
});
```

Calling methods such as `get()`, `post()` with a function call-back will register them and if URL matches a pattern, all the matching callbacks will be executed, that is, until some of them will present a return value. 

Execution will occur as soon as the match is confirmed (to help with error display).

Technically this allows multiple call-backs to be matched:

``` php
$app->get('/:method', function($method) {
    // do something 
});

$app->get('/ping', function() {
    return 'pong';
});
```

Note, that some popular PHP API frameworks (like Slim) use {name} for matching parameters, however rest of IT industry prefers using ":name" instead. We will use industry pattern matching, but will try to also support {$foo}, although it does look too similar to Agile UI template tags.

I think that the methods can be cleverly made to match the rules too:

``` php
function get($route, $action) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    	return $this->match($route, $action);
    }
}
```

A useful note about `match` is that it can be used without action and will return `true`/`false`.

``` php
if ($app->match('/misc/**')) {
    // .. execute logic for requests starting with /misc/...
} else {
    // .. other logic
}
```

### Model Routing

Method `rest()` implements a standard Restful API end-point dedicated to a model. There are two ways to use it:

``` php
$app->rest('/clients', new Client($db));
```

This would simple enable all the necessary operations for accessing the model, in particular:

-   GET /clients - listing all clients
-   GET /clients/:id - get specific client data
-   POST /clients - create new client
-   PUT /clients/:id - same as patch
-   PATCH /clients/:id - load, update specified fields only, save
-   DELETE /clients/:id - delete specific client record

You can also specify a different field if you don't want to use primary key:

``` php
$app->rest('/country/:iso_name');
```

Agile Data offers powerful ways of traversing references, and the above approach can also utilize:

``` php
$app->rest('/clients/:id/orders/::Orders:id', new Client($db));
```

This would create new route for URLs such as `/clients/123/orders/395`. The model for the client with id 123 would be loaded first, then ref('Orders') would be executed. The rest of the logic is similar to before.

This gives us option to perform deep traversal too:

``` php
$app->rest('/clients/:id/order_payments/::Orders::Payments:id', new Client($db));
```

This would load the Client, perform ref('Orders')->ref('Payments'). Finally, the "id" is optional:

```php
$app->rest('/client/:/order_payments/::Orders::Payments', new Client($db));
```

Sometimes you would want to have even more control, so you can use:

``` php
$app->rest('/client/:id/invoices-due', function($id) use($db) {
    $client = new Client($db);
    $client->load($id);
    return $client->ref('Invoices')->addCondition('status', 'due');
});
```

Method `rest()` builds on top of methods `put()`, `get()`, `post()` and others. Third argument to method `rest()` can specify array with options.

## Auth

Our API supports various authentication methods. Some of them are built-in and 3rd party extensions can also be used.

Lets look at the very basic user/password authentication.

``` php
// Enable user/password authentication. Field values are optional
$app->userAuth('/**', new User($db));
```

You can place the authentication method strategically, and it will protect all the further routes but not the ones above it. Also you can use a custom route if you wish to only protect some portion of your API.

The method AUTH will look for HTTP_AUTH headers and will respond with 405 code if user record cannot be loaded with a corresponding user/password combination.

After user authentication is performed, `$app->user` will exist:

``` php
$app->authUser('/**', new User($db));
$app->rest('/notifications', $app->user->ref('Notifications'));
```

### Rate Limit

Rate Limit support will limit number of requests which user (or IP) can make. It's easy to set it up:

``` php
$app->authUser('/**', new User($db));

$limit = new \atk4\api\Limit($db);
$limit->addCondition('user_id', $app->user->id);
  
$app->get('/limits', function() use ($limit){ 
    return $limit;
});

$app->rateLimit('/**', $limit, 10);  // 10 requests per minute

$app->rest('/notifications', $app->user->ref('Notifications'));
```

It's preferable to use rate limits with persistence such as Redis or Memcache:

``` php
$cache = \atk4\data\Persistence\MemCache($conn);
$limit = new \atk4\api\Limit($cache);
```

### Deep logging

Agile Data already supports audit log, but with Agile API you can compliment that even further:

``` php
$audit_id = $app->auditLog(
  '/**', 
  new \atk4\audit\Controller(
    new \atk4\audit\model\AuditLog($db)
  )
);
```

This would create a log entry per invocation and use it for all the subsequent changes inside data persistence.

Note that the `$audit_id` produced by the above function can also be used for UNDO action:

``` php
$app->auditLog->load($audit_id)->undo();
```

which would also reverse all the changes done on the persistence layer.

### Error Logging

Similarly to Agile UI, the application for API will catch exceptions raised.

``` php
$app->?
```

### System support and global scoping

Agile Data supports global scoping, so you can add additional hook that would affect creation of all the models and add some further conditioning. That's useful based off the Auth response:

``` php
$user_id = $app->authUser('/**', new User($db));

$db->addHook('afterAdd', function($o, $e) use ($user_id) {
    if ($e->hasElement('user_id')) {
        $e->addCondition('user_id', $user_id);
    }
})

```

### Mapping to file-system

``` php
$app->map('/:resource/**', function(resource) use($app) {
  
    // convert user-credit to UserCredit
    $class = preg_replace('/[^a-zA-Z]/', '', ucwords($resoprce));
  
  	$object = $app->factory($class, null, 'Interface'); // Interface\UserCredit.php
  
  	return [$object, $app->method]; 
    // convert path to file
    // load file
    // create class instance
    // call method of that class
  
    // TODO: think of some logical example here!!
});
```



### Optional Arguments

Agile API supports various get arguments.

-   `?sort=name,-age` specify columns to sort by. 
-   `?q=search`, will attempt to perform full-text search by phrase. (if supported by persistence)
-   `?condition[name]=value`, conditioning, but can also use `?name=value`
-   `?limit=20`, return only 20 results at a time.
-   `?skip=20`, skip first 20 results.
-   `?only=name,surname` specify onlyFields
-   `?ad={transformation}`, apply Agile Data transformation

Handling of those arguments happens inside function `args()`. It's passed in a Model, so it will look at the GET arguments and perform the necessary changes. 

``` php
function args(\atk4\data\Model $m) {
    if ($_GET['sort']) {
        $m->sortBy($_GET['sort']);
    }
  
    if ($_GET['condition']) {
    	foreach($_GET['condition'] as $key=>$val) {
            $m->addCondition($key, $val);
        }
    }
  
    if ($_GET['limit'] || $_GET['skip']) {
        $m->setLimit($_GET['limit']?:null, $_GET['skip']?:null);
    }
  
    // etc. etc...
}
```

### Other points

Agile API is JSON only. You might be able to add XML output, but why.

Agile API does not use envelope. Response data will be "[]" for empty result. If there is a problem with response, you'll get it through status code, in which case output will change.

Agile API does not support HATEOAS. Technically you should be able to add support for it, but it would require a more complex mapping or extra code. We prefer to keep things simple.

Agile API will pretty-print JSON by default, so make sure "gzip" is enabled.

Agile API will accept either raw JSON or Form encoded input, but examples will always use JSON

Agile API does not use "pagination" instead "limit" and "skip" values. You can introduce pages if you wish.

Deep-loading resources is something that you can add. For instance if you load "Invoice" it may contain "lines" array containing list of hashes. Documentation will be provided on how to make this possible. There will also get argument to instruct if deep-loading is needed.

Errors and exceptions will contain "error", "message" and "args" keys. Optional key "raised_by" may contain another object with same keys if said error was raised by another error. Another possibility is "description" field.

(see http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api)

https://www.reddit.com/r/PHP/comments/32tbxs/looking_for_php_rest_api_framework/

testing / behat: http://restler3.luracast.com/examples/_001_helloworld/readme.html

### URL patterns

Here are some examples

-   `/user/:id`  matches /user/123 , /user/123/ , /user/abc/ but won't match /user/123/x
-   `/user/:` same as above
-   `/user/:/:` matches /user/123/321 but won't match /user/123
-   `/user/*/:` matches /user/blah/123 but will ignore blah
-   `/user/**/:` incorrect, as `**` must be last.
-   `/user/:/**` matches /user/123/blah and /user/123/foo/blah and /user/123
-   `/user/:id/:action?` optional parameter. If unspecified will be null

### Route Groups

It's possible to divert route group to a different App.

``` php
$app = new \atk4\app\Api();

$app->group('/user/**', function($app2) {
   $app2->get('/test', function() {
     return 'yes';
   });
});
```

You can also divert 
