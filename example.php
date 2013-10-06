<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * For the full copyright and license information, please view the LICENSE
 *
 * @category    blackakula
 * @package     wdebug
 * @copyright   Copyright (c) Sergii Akulinin <blackakula@gmail.com>
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
# Server run example:
# java -Dwebdriver.chrome.driver="W:\chromedriver.exe" -jar selenium-server-standalone-2.32.0.jar
include 'vendor/autoload.php';
$host = 'http://localhost:4444/wd/hub';
$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'chrome');
$driver = new Wdebug\RemoteWebDriver($host, $capabilities);
$driver->get('http://example.com/');
$debugger = new Wdebug\Debug($driver);
$debugger->execute();
$driver->quit();

