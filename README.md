WDebug. Debugger for Webdriver
======

## Description

WDebug was developed to simplify writing, supporting and debugging locators (xpath, css selector, etc) for webdriver.

With this tool you can add, edit and remove locators during your automation script execution. Integrate tool with your framework - and you'll be able to save changed locators.

Tool uses [facebook/webdriver](https://github.com/facebook/php-webdriver) bindings for PHP.

See WDebug tool integrated with custom framework in action on youtube: http://www.youtube.com/watch?v=0-PTAFEce60.

## Installation

1.  git clone https://github.com/blackakula/wdebug.git

2.  If you are using Packagist, add the dependency. https://packagist.org/packages/blackakula/wdebug

        {
          "require": {
            "blackakula/wdebug": ">=0.1"
          }
        }

3. Start using [facebook/webdriver](https://github.com/facebook/php-webdriver)

## Features

See [the video](http://www.youtube.com/watch?v=0-PTAFEce60) for functional abilities.

Tool consists of one class with 3 public methods

* setStyle(), deleteStyle() are used to prepare custom styles for highlighting page elements.
* execute() starts debugging tool: show popup and wait for user actions until popup is closed. Here you can pass callback on all user actions (add/delete locator/placeholder) to integrate tool with your custom framework.
