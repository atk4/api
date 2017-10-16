# Agile API Framework

End-to-end implementation for your REST API. Provides a very simple means for you to define API end-points for the application that already uses [Agile Data](https://github.com/atk4/data).

## Planned Features

Agile API is in development but the following features are planned:

-   [ ] Simple to use.


-   [ ] Model routing. Provide end-points by associating them with models.
-   [ ] Global authentication. Provide authentication strategy for entire framework.
-   [ ] Support for rate limits. Per-account, per-IP counters which can be stored in MemCache or Redis.
-   [ ] Deep logging, integrated with data persistence. Not only stores the request, but what data was affected inside persistence.
-   [ ] Support for API UNDO. Neutralize effect of API call had on your backend.

### Simple to use

To set up your API, simply create new RestAPI class instance and define routes. You can enable versioning by creating "v1" folder and placing `index.php` in that folder. Some things work and we do not want to re-invent them!

``` php
require 'vendor/autoload.php';
$app = new \atk4\api\RestAPI.php;

$db = \atk4\data\Persistence::connect($DSN);

// Lets set our index page
$app->get('/', function() {
    return 'This worked!';
});

// Getting access to raw post
$app->post('/stats/:id', function($id)) {
    return ['Received POST', 'id'=>$id, 'post_data'=>file_get_contents('php://input')];
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

