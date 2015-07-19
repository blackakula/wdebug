<?php
use \Facebook\WebDriver\Remote as FBRemote;
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
$driver = FBRemote\RemoteWebDriver::create($host, FBRemote\DesiredCapabilities::chrome());
$driver->get('http://example.com/');
$debugger = new Wdebug\Debug($driver);
$debugger->execute(function ($type, $data) {
    switch ($type) {
        case 'deleteLocator':
            echo 'Deleted locator "' . $data['key'] . "\"\n";
            break;
        case 'setLocator':
            if (empty($data['oldKey'])) {
                echo 'Added locator "' . $data['key'] . "\"\n";
            } else {
                echo 'Changed locator "' . $data['oldKey'] . '" -> "' . $data['key'] . "\"\n";
            }
            echo '  - type:  ' . $data['locatorType'] . "\n";
            echo '  - value: ' . $data['locatorValue'] . "\n";
            break;
        case 'deletePlaceholder':
            echo 'Deleted placeholder "' . $data['key'] . "\"\n";
            break;
        case 'addPlaceholder':
            echo 'Added placeholder "' . $data['key'] . "\"\n";
            break;
    }
});
$driver->quit();

