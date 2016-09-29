# Examples

These examples assume that Composer's autoloading is enabled and namespace `Ranvis\RobotsTxt` is imported. i.e.:

```php
use Ranvis\RobotsTxt;

require_once(__DIR__ . '/vendor/autoload.php');
```

## Testing if a path is allowed to crawl

```php
$source = <<<'END'
User-agent: *
Disallow:

user-agent:MyBot
disallow  :*.php
END;
$filter = new RobotsTxt\Filter();
$filter->setUserAgents(['MyBot-Preview', 'MyBot']); // in the order of precedence
$recordSet = $filter->getRecordSet($source);
$tester = new RobotsTxt\Tester();
$tester->setSource($recordSet);
var_dump($tester->isAllowed('/test.php')); // false

$tester->setSource($source, ['MyBot-Preview', 'MyBot']); // shorthand
var_dump($tester->isAllowed('/test.php')); // false

$tester->setSource($recordSet);
var_dump($tester->isAllowed('/test.php', 'MyBot-Preview')); // true

$tester->setResponseCode(500);
var_dump($tester->isAllowed('/test.php', 'MyBot-Preview')); // false
```

Both `setSource()` and `setResponseCode()` overwrite each other.

## Getting values like `Crawl-delay`

To retrieve values tied to User-agent, use `getValue` or `getValueIterator`.
The former one returns the first value of the requested directive (or null,) while the other returns iterator that may be empty.

```php
$source = <<<'END'
User-agent: *
Crawl-delay: 30

User-agent: mybot
Crawl-delay: 60
END;
$filter = new RobotsTxt\Filter();
$recordSet = $filter->getRecordSet($source);
$record = $recordSet->getRecord('MyBot');
var_dump($record->getValue('Crawl-delay'));
// string(2) "60"

$record = $recordSet->getRecord('Foo');
var_dump(iterator_to_array($record->getValueIterator('crawl-delay')));
// array(1) {
//   [0]=> string(2) "30"
// }
```

Here uses `iterator_to_array()` for illustration purpose. You can of course pass it to `foreach`.

## Getting custom values tied to user-agent

Using custom directive is a little complex.
Instantiate `FilterParser`, register your directive with `registerGroupDirective()`, then feed `getRecordIterator()` result as a filter source.
Once you obtain a record with `getNonGroupRecord()`, you get values with `getValue()` or `getValueIterator()`.
If you haven't register, unknown directives are treated as non-group directive (see the next example.)

```php
$source = <<<'END'
User-agent: Mybot
Crawl-delay: 60
my-custom-value: 30
my-custom-flag: yes
END;
$parser = new RobotsTxt\FilterParser();
$parser->registerGroupDirective('My-custom-flag'); // A-Z, 0-9 and hyphen only
$filter = new RobotsTxt\Filter();
$filter->setUserAgents('MyBot');
$recordSet = $filter->getRecordSet($parser->getRecordIterator($source));
$record = $recordSet->getRecord(); // with $filter->setUserAgents() you can safely skip specifying user-agent here
var_dump($record->getValue('My-custom-flag'));
// string(3) "yes"

var_dump($record->getValue('my-custom-value')); // need registerGroupDirective()
// NULL
```

## Getting non-group values like `Sitemap`

```php
$source = <<<'END'
User-agent: *
Crawl-delay: 30

Clean-param: dd&back *.php
Host: www.example.com
Sitemap: http://www.exmample.com/sitemap.xml
Sitemap: http://www.exmample.com/sitemap-2.xml
END;
$filter = new RobotsTxt\Filter();
$recordSet = $filter->getRecordSet($source);
$record = $recordSet->getNonGroupRecord();
var_dump(iterator_to_array($record->getValueIterator('Sitemap'), false));
// array(2) {
//   [0]=> string(35) "http://www.exmample.com/sitemap.xml"
//   [1]=> string(37) "http://www.exmample.com/sitemap-2.xml"
// }

var_dump($record->getValue('Host')); // getting the first value only
// string(15) "www.example.com"

var_dump(iterator_to_array($record->getValueIterator('Clean-param'), false));
// array(1) {
//   [0]=> string(13) "dd&back *.php"
// }
```

## Prefilter of a random robots.txt module

You can let `Tester` class alone and combine `Filter` class with another tester class.
Below example uses `Filter` to work in conjunction with `diggin/diggin-robotrules`.

```php
$source = <<<'END'
User-agent: *
Disallow:

user-agent:MyBot
disallow: *.php

sitemap: http://www.example.com/sitemap.xml
END;
$filter = new RobotsTxt\Filter();
$filter->setUserAgents(['MyBot-Image', 'MyBot']); // in the preferred order
$recordSet = $filter->getRecordSet($source);
$source = (string)$recordSet->extract(); // Get the most matching record as an `*` record, append the non-group record.
var_dump($source);
// string(79) "User-agent: *
// Disallow: *.php
//
// Sitemap: http://www.example.com/sitemap.xml
// "

$accepter = new \Diggin\RobotRules\Accepter\TxtAccepter();
$accepter->setRules(\Diggin\RobotRules\Parser\TxtStringParser::parse($source));
$accepter->setUserAgent('AnythingIsOkHere');
var_dump($accepter->isAllow('/test.php')); // false
```
