# robots.txt filter and tester for untrusted source

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->


- [Introduction](#introduction)
- [License](#license)
- [Installation](#installation)
- [Example Usage](#example-usage)
- [Implementation Notes](#implementation-notes)
  - [Setting user-agents](#setting-user-agents)
  - [Record separator](#record-separator)
  - [Case sensitivity](#case-sensitivity)
  - [Encoding conversion](#encoding-conversion)
  - [Features](#features)
- [Options](#options)
  - [`Tester` class options](#tester-class-options)
  - [`Filter` class options](#filter-class-options)
  - [`FilterParser` class options](#filterparser-class-options)
  - [`Parser` class options](#parser-class-options)
- [Interface](#interface)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Introduction

robots-txt-processor is a tester with a filter for natural wild robots.txt data of the Internet.
The module can filter like:

- Rules for other User-agents
- Rules that are too long
- Paths that contains too many wildcards
- Comments (inline or the whole line)

Also, it can for example:

- Parse line continuation (LWS,) although not used widely
- Identify misspelled `Useragent` directive
- Complement missing leading slash in a path

Tester module can process Allow/Disallow directives containing `*`/`$` meta characters.
Alternatively, you can use the filter module alone and feed an output to another tester module as a single `User-agent: *` record with a non-group record (e.g. Sitemap.)

## License

BSD 2-Clause License


## Installation

`
composer require "ranvis/robots-txt-processor:^1.0"
`


## Example Usage

```php
require_once __DIR__ . '/vendor/autoload.php';

$source = "User-agent: *\nDisallow: /path";
$userAgents = 'MyBotIdentifier';
$tester = new \Ranvis\RobotsTxt\Tester();
$tester->setSource($source, $userAgents);
var_dump($tester->isAllowed('/path.html')); // false
```

`Tester->setSource(string)` is actually a shorthand of `Tester->setSource(RecordSet)`:

```php
use Ranvis\RobotsTxt;

$source = "User-agent: *\nDisallow: /path";
$userAgents = 'MyBotIdentifier';
$filter = new RobotsTxt\Filter();
$filter->setUserAgents($userAgents);
$recordSet = $filter->getRecordSet($source);
$tester = new RobotsTxt\Tester();
$tester->setSource($recordSet);
var_dump($tester->isAllowed('/path.php')); // false
```

See [EXAMPLES.md](EXAMPLES.md) for more examples, including filter-only usage.


## Implementation Notes

### Setting user-agents

When setting source, you can (optionally) pass user-agents like the examples above.
If you pass a user-agent string or an array of strings, subsequent `Filter` will filter out unspecified user-agent records (aside from `*`.)
While `Tester->isAllowed()` accepts user-agents, it should run faster to filter (with `Filter->setUserAgents()` or `Tester->setSource(source, userAgents)`) and call `Tester->isAllowed()` multiple times without specifying user-agents.
(When an array of user-agent strings is passed, a user-agent specified earlier takes precedence when testing.)

### Record separator

This parser ignores blank lines. Another record starts on User-agent lines after group member lines (i.e. `Disallow`/`Allow`.)

### Case sensitivity

`User-agent` value and directive names like `Disallow` are case-insensitive.
`Filter` class normalizes directive names to First-character-uppercased form.

### Encoding conversion

This filter/tester themselves don't handle encoding conversion because it isn't needed.
If a remote robots.txt uses some non-Unicode (specifically not UTF-8) encoding, URL path should be in that encoding too.
The filter/tester safely work with any character or percent-encoded sequence which can result in invalid UTF-8.
An exception is when a remote robots.txt uses any Unicode encoding with BOM. If this will ever happen, you will need to convert it to UTF-8 (without BOM) beforehand.

### Features

See [features/behaviors table](https://github.com/ranvis/robots-txt-processor-test/wiki/Features) of robots-txt-processor-test project.


## Options

Options can be specified in the first argument of constructors.
Normally, the default values should suffice to filter potentially offensive input while preserving requested rules.

### `Tester` class options
- `'respectOrder' => false,`

  If true, process path rules in their specified order.
  If false, longer path is processed first like Googlebot does.

- `'ignoreForbidden' => false,`

  If true, `setResponseCode()` with `401 Unauthorized` or `403 Forbidden` is treated as if no robots.txt existed, like Googlebot does, as opposed to robotstxt.org spec.

- `'escapedWildcard' => false,`

  If true, `%2A` in path line is treated as wildcard `*`.
  Normally you don't want to set this true for this class. See `Filter` class for some more information.

`Tester->setSource(string)` internally instantiates `Filter` with initially passed options and calls `Filter->getRecordSet(string)`.

### `Filter` class options
- `'maxRecords' => 1000,`

  Maximum number of records (grouped rules) to parse.
  Any records thereafter will not be kept.
  Don't set too low or filter will give up before your user-agents.
  This limitation is only for parsing. Calling `setUserAgents()` limits what user-agents to keep.

`Filter->getRecordSet(string)` internally instantiates `FilterParser` with initially passed options.

### `FilterParser` class options

- `'maxLines' => 1000,`

  Maximum number of lines to parse for each record (grouped or non-grouped).
  Any lines thereafter for the current record will not be kept.

- `'keepTrailingSpaces' => false,`

  If false, trailing spaces (including tabs) of line without comment is trimmed. For lines with comment, spaces before `#` are always trimmed.
  Retaining spaces is the requirement of both robotstxt.org and Google specs.

- `'maxWildcards' => 10,`

  Maximum number of non-repeated `*` in path to accept.
  If a path contains more than this, the rule itself will be ignored.

- `'escapedWildcard' => true,`

  If true, `%2A` in path line is treated as wildcard `*` and will be a subject to the limitation of `maxWildcards`.
  When using an external tester, don't set to false unless you are sure that your tester doesn't treat `%2A` that way (and this tester does not,) so that rules cannot circumvent `maxWildcards` limitation.
  (Testers listed as PeDecodeWildcard=yes in [feature test table](https://github.com/ranvis/robots-txt-processor-test/wiki/Features) should not change this flag.)

- `'complementLeadingSlash' => true,`

  If true and the path doesn't start with `/` or `*` (which must be a mistake,) `/` is prepended.

- `'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',`

  A value of a directive matching this regex is treated as a path and configurations like `maxWildcards` are applied.

`FilterParser` extends `Parser` class.

### `Parser` class options
- `'maxUserAgents' => 1000,`

  Maximum number of user-agents to parse.
  Any user-agents thereafter will be ignored and any new grouped records thereafter will be skipped.

- `'maxDirectiveLength' => 32,`

  Maximum number of characters for the directive.
  Any directives longer than this will be skipped.
  This must be at least 10 to parse `User-agent` directive.
  Increase if you need to keep custom long named directive value.

- `'maxNameLength' => 200,`

  Maximum number of characters for the `User-agent` value.
  Any user-agent names longer than this are truncated.

- `'maxValueLength' => 2000,`

  Maximum number of characters for the directive value.
  Any values longer than this will be changed to `-ignored-` directive with a value containing the original value length.

- `'userAgentRegEx' => '/^User-?agent$/i',`

  A directive matching this regex is treated as a `User-agent` directive.


## Interface

- `new Tester(array $options = [])`
- `Tester->setSource($source, $userAgents = null)`
- `Tester->setResponseCode(int $code)`
- `Tester->isAllowed(string $targetPath, $userAgents = null)`
- `new Filter(array $options = [])`
- `Filter->setUserAgents($userAgents, bool $fallback = true) : RecordSet`
- `Filter->getRecordSet($source) : RecordSet`
- `new Parser(array $options = [])`
- `Parser->registerGroupDirective(string $directive)`
- `Parser->getRecordIterator($it) : \Traversable`
- `(string)RecordSet`
- `RecordSet->extract($userAgents = null)`
- `RecordSet->getRecord($userAgents = null, bool $dummy = true) : ?RecordSet`
- `RecordSet->getNonGroupRecord(bool $dummy = true) : ?RecordSet`
- `(string)Record`
- `Record->getValue(string $directive) : ?string`
- `Record->getValueIterator(string $directive) : \Traversable`

