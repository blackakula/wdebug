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
namespace Wdebug;

class RemoteWebDriver extends \RemoteWebDriver
{
    /**
     * Prepare arguments for JavaScript injection
     *
     * @param array $arguments
     * @return array
     */
    private function prepareScriptArguments(array $arguments)
    {
        $args = array();
        foreach ($arguments as $arg) {
            if ($arg instanceof \WebDriverElement) {
                array_push($args, array('ELEMENT' => $arg->getID()));
            } else {
                if (is_array($arg)) {
                    $arg = $this->prepareScriptArguments($arg);
                }
                array_push($args, $arg);
            }
        }
        return $args;
    }

    /**
     * @inheritdoc
     */
    public function executeScript($script, array $arguments = array()) {
        $params = array('script' => $script, 'args' => $this->prepareScriptArguments($arguments));
        $response = $this->executor->execute('executeScript', $params);
        return $response;
    }
}
