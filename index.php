<?php
/** 
 * APILogWriter: Custom log writer for our application
 *
 * We must implement write(mixed $message, int $level)
*/
class APILogWriter {
	public function write($message, $level = \Slim\Log::DEBUG) {
		echo $level.': '.$message.'<br />';
	}
}

class ResourceNotFoundException extends Exception {};

require 'vendor/autoload.php';
require 'vendor/rb.php';

// set up database connection
R::setup('mysql:host=localhost;dbname=api_rest','root','default');
R::freeze(true);

// route middleware for simple API authentication
function authenticate(\Slim\Route $route) {
    $app = \Slim\Slim::getInstance();
    $uid = $app->getEncryptedCookie('uid');
    $key = $app->getEncryptedCookie('key');
    if (validateUserKey($uid, $key) === false) {
      $app->halt(401);
    }
}

function validateUserKey($uid, $key) {
  // insert your (hopefully more complex) validation routine here
  if ($uid == 'demo' && $key == 'demo') {
    return true;
  } else {
    return false;
  }
}



$app = new \Slim\Slim(array(
	'mode' => 'development',
	'log.enabled' => true,
	'log.level' => \Slim\Log::DEBUG,
	'log.writer' => new APILogWriter(),
    'templates.path' => 'views'
));



$app->get('/demo', function () use ($app) {    
  try {
    $app->setEncryptedCookie('uid', md5('demo'), '5 minutes');
    $app->setEncryptedCookie('key', md5('demo'), '5 minutes');
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->get('/users/:name',  function($name) use ($app) {
    $app->render('users.php', array('data' => $name, 'view' => true));
});

$app->get('/articles', function() use ($app) {
    // query database for all articles
    $articles = R::find('articles'); 

    // send response header for JSON content type
    $app->response()->header('Content-Type', 'application/json');

    // return JSON-encoded response body with query results
    echo json_encode(R::exportAll($articles));
});

$app->get('/articles/:id', function ($id) use ($app) {    
  try {
    // query database for single article
    $article = R::findOne('articles', 'id=?', array($id));
    
    if ($article) {
      // if found, return JSON response
      $app->response()->header('Content-Type', 'application/json');
      echo json_encode(R::exportAll($article));
    } else {
      // else throw exception
      throw new ResourceNotFoundException();
    }
  } catch (ResourceNotFoundException $e) {
    // return 404 server error
    $app->response()->status(404);
    $r = array('success'=>false);
    echo json_encode($r);

  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
})->conditions(array('id' => '[0-9]{1,}'));


// handle POST requests to /articles
$app->post('/article', 'authenticate', function () use ($app) {    
  try {   
    // get and decode JSON request body
    $request = $app->request->params();
    $body = json_encode($request); 
    $input = json_decode($body);

    // store article record
    $article = R::dispense('articles');
    $article->title = (string)$input->title;
    $article->url = (string)$input->url;
    $article->date = (string)$input->date;
    $id = R::store($article);    


    // return JSON-encoded response body
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(R::exportAll($article));
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

// handle PUT requests to /articles/:id
$app->put('/article/:id', 'authenticate', function ($id) use ($app) {    
  try {
    // get and decode JSON request body
    $request = $app->request->put();
    $body = json_encode($request); 
    $input = json_decode($body);


    // query database for single article
    $article = R::findOne('articles', 'id=?', array($id));  
    
    // store modified article
    // return JSON-encoded response body
    if ($article) {      
      $article->title = (string)$input->title;
      $article->url = (string)$input->url;
      $article->date = (string)$input->date;
      R::store($article);    
      $app->response()->header('Content-Type', 'application/json');
      echo json_encode(R::exportAll($article));
    } else {
      throw new ResourceNotFoundException();    
    }
  } catch (ResourceNotFoundException $e) {
    $app->response()->status(404);
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

// handle DELETE requests to /articles/:id
$app->delete('/article/:id', function ($id) use ($app) {    
  try {
    // query database for article
    $request = $app->request();
    $article = R::findOne('articles', 'id=?', array($id));  
    
    // delete article
    if ($article) {
      R::trash($article);
      $app->response()->status(200);
      $r = array('success'=>true);
      echo json_encode($r);

    } else {
      throw new ResourceNotFoundException();
    }
  } catch (ResourceNotFoundException $e) {
    $app->response()->status(404);
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->run();
