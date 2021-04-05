<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/



$router->get('/',  ['uses' => 'HelpController@showHelpMain']);
$router->get('help',  ['uses' => 'HelpController@showHelpMain']);
$router->get('/version', function () use ($router) {
    return $router->app->version()  ; 
});

$router->get('say/{id}', function ($id) {
    return 'say: '.$id;
});

$router->get('mock', function () {
    $table = [ 1,2 ];
    return $table;
});

/*$router->get('admin/profile', ['middleware' => 'auth', function () {
    //
}]);*/



//Users routes

$router->group(['prefix' => '/user'], function () use ($router) {
    $router->get('help', ['uses' => 'HelpController@showHelpUser']);
    $router->get('', ['uses' => 'UserController@showAllUsers']);
    $router->get('{id}', ['uses' => 'UserController@showOneUser']);
    $router->post('', ['uses' => 'UserController@create']);
    $router->post('{id}', ['uses' => 'UserController@update']);
    $router->delete('{id}', ['uses' => 'UserController@delete']);
});


    $router->post('createemail', ['uses' => 'ISPController@createEmail']);
    $router->post('updateemail', ['uses' => 'ISPController@updateEmail']);
    $router->post('deleteemail', ['uses' => 'ISPController@deleteEmail']);

// Messages routes

$router->get('/unpack/{arg}',  ['uses' => 'MessageController@unpackInboxMessage']);


$router->get('/messages',  ['uses' => 'MessageController@showAllMessages']);

$router->group(['prefix' => '/message'], function () use ($router) {
    $router->get('', ['uses' => 'MessageController@showAllMessages']);
    $router->get('help', ['uses' => 'HelpController@showHelpMessage']);
    $router->get('list',  ['uses' => '@showAllMessages']);
    $router->post('', ['uses' => 'MessageController@sendHMP']);
    $router->delete('{id}', ['uses' => 'MessageController@deleteMessage']);
    $router->post('{id}', ['uses' => 'MessageController@update']);
    $router->get('{id}', ['uses' => 'MessageController@showOneMessage']);
    $router->get('image/{id}', ['uses' => 'FileController@get']);
    $router->get('send/{id}',  ['uses' => 'MessageController@sendMessage']);
});

$router->group(['prefix' => '/inbox'], function () use ($router) {
    $router->get('help', ['uses' => 'HelpController@showHelpInbox']);
    $router->get('', ['uses' => 'MessageController@showAllInboxMessages']);
    $router->get('{id}', ['uses' => 'MessageController@showOneInboxMessage']);
    $router->get('image/{id}', ['uses' => 'MessageController@showOneInboxMessageImage']);
    $router->get('delete/{id}', ['uses' => 'MessageController@deleteInboxMessage']);
    $router->get('hide/{id}', ['uses' => 'MessageController@hideInboxMessage']);
    $router->get('unhide/{id}', ['uses' => 'MessageController@unhideInboxMessage']);
});


//TODO double check
$router->group(['prefix' => '/file'], function () use ($router) {
    $router->get('', ['uses' => 'FileController@showAllFiles']);
    $router->post('', ['uses' => 'FileController@uploadImage']);
    $router->get('up/{id}', ['uses' => 'FileController@getImageUploadHttp']);
    $router->get('down/{id}', ['uses' => 'FileController@getImageDownloadHttp']);
});

/*
    $router->get('files', ['uses' => 'FileController@showAllFiles']);
    $router->post('file', ['uses' => 'FileController@uploadImage']);
    $router->get('file/{id}', ['uses' => 'FileController@getImageHttp']);
*/


$router->get('help',  ['uses' => 'HelpController@showHelpSys']);

// system commands
$router->post('login', ['uses' => 'UserController@login']); //TODO remove

$router->group(['prefix' => '/sys'], function () use ($router) {
    $router->post('login', ['uses' => 'UserController@login']); 

    $router->get('status',  ['uses' => 'SystemController@getSysStatus']);
    $router->get('nodename',  ['uses' => 'SystemController@getSysNodeName']);
    $router->get('help',  ['uses' => 'HelpController@showHelpSys']);
    $router->get('run/{command}',  ['uses' => 'SystemController@exec_cli']);
    $router->get('ls',  ['uses' => 'SystemController@getFiles']);
    $router->get('list',  ['uses' => 'SystemController@systemDirList']);
    $router->get('queueerase',  ['uses' => 'SystemController@queueErase']);
    $router->get('spool',  ['uses' => 'SystemController@sysGetSpoolList']);
    $router->get('stations',  ['uses' => 'SystemController@getSysStations']);
    $router->get('restart',  ['uses' => 'SystemController@systemRestart']);
    $router->get('killuucp',  ['uses' => 'SystemController@uucpJobsKill']);
    $router->get('jobs',  ['uses' => 'SystemController@uucpJobList']);
    $router->get('shutdown',  ['uses' => 'SystemController@sysDoShutdown']);
    $router->get('getlog',  ['uses' => 'SystemController@sysGetLog']);

});

$router->group(['prefix' => '/radio'], function () use ($router) {
    $router->post('login', ['uses' => 'UserController@login']); 

    $router->get('status',  ['uses' => 'RadioController@getRadioStatus']);
    $router->get('mode',  ['uses' => 'RadioController@getRadioMode']);
    $router->post('mode',  ['uses' => 'RadioController@setRadioMode']);
    $router->get('freq',  ['uses' => 'RadioController@getRadioFreq']);
    $router->post('freq',  ['uses' => 'RadioController@setRadioFreq']);
    $router->get('bfo1',  ['uses' => 'RadioController@getRadioBfo']);
    $router->post('bfo1',  ['uses' => 'RadioController@setRadioBfo']);
    
});



