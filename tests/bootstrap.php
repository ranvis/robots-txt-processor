<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

$loader = require_once(__DIR__ . '/../vendor/autoload.php');
$loader->addPsr4('Ranvis\RobotsTxt\\', __DIR__);

function getInstanceMethod($instance, string $method) : Callable
{
    return (new \ReflectionMethod($instance, $method))->getClosure($instance);
}

function nlToCrlf(string $string) : string
{
    return str_replace("\n", "\x0d\x0a", $string);
}

function toLine(string $field, string $value) : array
{
    return ['field' => $field, 'value' => $value];
}
