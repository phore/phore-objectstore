<?php

namespace App;
use Phore\MicroApp\App;
use Phore\MicroApp\Handler\JsonExceptionHandler;
use Phore\MicroApp\Handler\JsonResponseHandler;
use Phore\MicroApp\Helper\CORSHelper;
use Phore\MicroApp\Type\QueryParams;
use Phore\MicroApp\Type\Request;
use Phore\MicroApp\Type\RouteParams;


require __DIR__ . "/../vendor/autoload.php";

$app = new App();
$app->activateExceptionErrorHandlers();
$app->setOnExceptionHandler(new JsonExceptionHandler());
$app->setResponseHandler(new JsonResponseHandler());

$app->assets()->addAssetSearchPath(__DIR__ . "/assets/");


/**
 ** Configure Access Control Lists
 **/
$app->acl->addRule(\aclRule()->route("/*")->ALLOW());


/**
 ** Configure Dependency Injection
 **/
$app->add("someParameterName", function () {
    return "Value";
});

/**
 ** Define Routes
 **/
$app->router->get("/", function (RouteParams $routeParams, Request $request) {
    return ["Hello World!"];
});

/**
 ** Run the application
 **/
$app->serve();
