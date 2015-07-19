<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * For the full copyright and license information, please view the LICENSE
 *
 * @category    blackakula
 * @package     wdebug
 * @copyright   Copyright (c) Sergii Kyrychenko <blackakula@gmail.com>
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Wdebug;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver;

class Debug
{
    /**
     * Debug window name (handle)
     */
    const WINDOW_NAME = 'Debug';

    /**
     * Timeout waiting popup is ready (seconds)
     */
    const POPUP_TIMEOUT = 5;

    /**
     * JavaScript popup window variable
     */
    const WINDOW_VARIABLE = 'debugWindow';

    /**
     * Facebook web driver
     *
     * @var \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected $driver;

    /**
     * Locators
     *
     * @var array
     */
    protected $locators;

    /**
     * Placeholders
     *
     * @var array
     */
    protected $placeholders;

    /**
     * Origin session window handle
     *
     * @var string
     */
    private $originWindow;

    /**
     * Styles for highlighting elements
     *
     * @var array
     */
    protected $highlightingStyles = array();

    /**
     * Argument for execute to revert highlighting
     *
     * @var array
     */
    private $highlightingRevertData;

    /**
     * Elements to highlight
     *
     * @var array
     */
    private $highlightingElements = array();

    /**
     * @param RemoteWebDriver $driver
     * @param array $locators $key => array($mechanism, $valueWithPlaceholders)
     * @param string[] $placeholders
     */
    public function __construct(RemoteWebDriver $driver, array $locators = array(), array $placeholders = array())
    {
        $this->driver = $driver;
        $this->highlightingStyles = array(
            'style.border' => function($key, WebDriver\WebDriverBy $locator) {return '2px dashed blue';},
            'title' => function ($key, WebDriver\WebDriverBy $locator) {
                return '"' . $key . '"[' . $locator->getMechanism() . ']: ' . $locator->getValue();
            }
        );
        $this->locators = $locators;
        $this->placeholders = $placeholders;
    }

    /**
     * Set style
     *
     * @param string $key
     * @param callback|string $style Callback should be like function($key, \WebDriverBy $locator)
     * @return $this
     */
    public function setStyle($key, $style)
    {
        if (!is_callable($style)) {
            $style = function($key, WebDriver\WebDriverBy $locator) use ($style) {return $style;};
        }
        $this->highlightingStyles[$key] = $style;
        return $this;
    }

    /**
     * Delete style
     *
     * @param string $key
     * @return $this
     */
    public function deleteStyle($key)
    {
        unset($this->highlightingStyles[$key]);
        return $this;
    }

    /**
     * Execute debugging
     *
     * @param callback|null $callback
     * @param int $timeout Timeout between checks
     */
    public function execute(callable $callback = null, $timeout = 500000)
    {
        $this->originWindow = $this->driver->getWindowHandle();
        $this->showPopup();
        do {
            $isDebugging = true;
            try {
                $this->processDebugActions($callback);
            } catch (WebDriver\Exception\WebDriverException $e) {
                $isDebugging = false;
            }
            if ((bool)$this->executeScriptFunction('isCheckRequested()')) {
                $this->executeScriptFunction('unmarkCheckRequest()');
                $placeholders = $this->executeScriptFunction('getPlaceholders()');
                $this->driver->switchTo()->window($this->originWindow);
                $this->revertHighlighting();
                $missedLocators = array();
                $isSingleChecker = $this->executeScriptFunction('isSingleChecker()');
                foreach ($this->getDebuggingLocators($placeholders) as $key => $locator) {
                    try {
                        if (count($this->driver->findElements($locator))) {
                            $elements = $isSingleChecker
                                ? array($this->driver->findElement($locator))
                                : $this->driver->findElements($locator);
                            foreach ($elements as $element) {
                                $this->addHighlightingElement($key, $locator, $element);
                            }
                        } else {
                            $missedLocators[] = $key;
                        }
                    } catch (WebDriver\Exception\WebDriverException $e) {
                        $missedLocators[] = $key;
                    }
                }
                $this->highlightElements();
                $this->executeScriptFunction('highlightLocators(' . json_encode($missedLocators) . ')');
            }
            usleep($timeout);
            $isDebugging = $isDebugging && ($this->isPopupFocused()
                || self::WINDOW_NAME == (string)$this->driver
                    ->executeScript('return window.' . self::WINDOW_VARIABLE . '.name'));
        } while ($isDebugging);
    }

    /**
     * Show debug popup window
     */
    protected function showPopup()
    {
        $this->driver->executeScript(
            'window.' . self::WINDOW_VARIABLE . ' = window.open("", '
                . json_encode(self::WINDOW_NAME) . ', "width=640,height=480,resizable,scrollbars");
                ' . self::WINDOW_VARIABLE . '.placeholders = arguments[0];
                ' . self::WINDOW_VARIABLE . '.locators = arguments[1];
                ' . self::WINDOW_VARIABLE . '.document.write('
                . json_encode(file_get_contents(__DIR__ . '/index.html')) . ');
                ' . self::WINDOW_VARIABLE . '.document.close();',
            array($this->placeholders, $this->locators)
        );
        $wait = new WebDriver\WebDriverWait($this->driver, self::POPUP_TIMEOUT);
        $wait->until(function () {
            return $this->isPopupReady();
        });
    }

    /**
     * Process changes on debug popup
     *
     * @param callback|null $callback
     */
    private function processDebugActions(callable $callback = null)
    {
        $actions = (array)$this->executeScriptFunction('popActions()');
        foreach ($actions as $action) {
            $actionType = $action['action'];
            unset($action['action']);
            switch ($actionType) {
                case 'deleteLocator':
                    unset($this->locators[$action['key']]);
                    break;
                case 'setLocator':
                    unset($this->locators[$action['oldKey']]);
                    $this->locators[$action['key']] = array($action['locatorType'], $action['locatorValue']);
                    break;
            }
            if (isset($callback)) {
                call_user_func($callback, $actionType, $action);
            }
        }
    }

    /**
     * Execute JavaScript function
     *
     * @param string $functionName
     * @return mixed
     */
    private function executeScriptFunction($functionName)
    {
        $window = $this->isPopupFocused() ? 'window.' : 'window.' . self::WINDOW_VARIABLE . '.';
        return $this->driver->executeScript('return ' . $window . $functionName . ';');
    }

    /**
     * Check if popup window is focused
     *
     * @return bool
     */
    private function isPopupFocused()
    {
        return $this->driver->getWindowHandle() == self::WINDOW_NAME;
    }

    /**
     * Check if popup is ready
     *
     * @return bool
     */
    private function isPopupReady()
    {
        $readyVariable = $this->isPopupFocused()
            ? 'window.debugIsReady'
            : 'window.' . self::WINDOW_VARIABLE . '.debugIsReady';
        return (bool)$this->driver->executeScript('return typeof(' . $readyVariable . ') != "undefined"');
    }

    /**
     * Add an element to highlighted
     *
     * @param string $key
     * @param WebDriver\WebDriverBy $locator
     * @param WebDriver\WebDriverElement $element
     */
    private function addHighlightingElement($key, WebDriver\WebDriverBy $locator, WebDriver\WebDriverElement $element)
    {
        $this->highlightingElements[] = array(
            'key'     => $key,
            'locator' => $locator,
            'element' => $element,
        );
    }

    /**
     * Highlight added elements
     */
    private function highlightElements()
    {
        if (empty($this->highlightingElements)) {
            $this->highlightingRevertData = null;
            return;
        }
        $i = 0;
        $originalDataScriptParts = array();
        $setStylesScriptParts = array();
        $scriptArguments = array();
        foreach ($this->highlightingElements as $value) {
            $originalDataScriptParts[$i] = $i . ':' . $this->getOriginalDataScript($i);
            $setStylesScriptParts[$i] = $this->setStylesDataScript($i, $value['key'], $value['locator']);
            $scriptArguments[$i] = $value['element'];
            ++$i;
        }
        $originalData = $this->driver
            ->executeScript('return {' . implode(',', $originalDataScriptParts) . '}', $scriptArguments);
        $revertScriptParts = array();
        foreach ($originalData as $i => $value) {
            $revertScriptParts[$i] = $this->revertStylesDataScript($i, $value);
        }
        $this->highlightingRevertData = array(
            'script' => implode(';', $revertScriptParts),
            'args' => $scriptArguments
        );
        $this->driver->executeScript(implode(';', $setStylesScriptParts), $scriptArguments);
        $this->highlightingElements = array();
    }

    /**
     * Revert highlighted elements to original style values
     */
    private function revertHighlighting()
    {
        if (isset($this->highlightingRevertData)) {
            $this->driver->executeScript(
                $this->highlightingRevertData['script'],
                $this->highlightingRevertData['args']
            );
            $this->highlightingRevertData = null;
        }
    }

    /**
     * Get JavaScript code part for getting element styles data
     *
     * @param int $i
     * @return string
     */
    protected function getOriginalDataScript($i)
    {
        $parts = array();
        foreach ($this->highlightingStyles as $style => $callback) {
            $parts[] = json_encode($style) . ':arguments[' . $i . '].' . $style;
        }
        return '{' . implode(',', $parts) . '}';
    }

    /**
     * Get JavaScript code part for setting element styles
     *
     * @param int $i
     * @param string $key
     * @param WebDriver\WebDriverBy $locator
     * @return string
     */
    protected function setStylesDataScript($i, $key, WebDriver\WebDriverBy $locator)
    {
        $parts = array();
        foreach ($this->highlightingStyles as $style => $callback) {
            $parts[] = 'arguments[' . $i . '].' . $style . '=' . json_encode($callback($key, $locator));
        }
        return implode(";", $parts);
    }

    /**
     * Get JavaScript code part for reverting element styles
     *
     * @param int $i
     * @param string $styles
     * @return string
     */
    protected function revertStylesDataScript($i, $styles)
    {
        $parts = array();
        foreach ($this->highlightingStyles as $style => $callback) {
            $parts[] = 'arguments[' . $i . '].' . $style . '=' . json_encode($styles[$style]);
        }
        return implode(";", $parts);
    }

    /**
     * Get debugging locators
     *
     * @param array $placeholders
     * @return WebDriver\WebDriverBy[]
     */
    protected function getDebuggingLocators(array $placeholders)
    {
        foreach ($placeholders as $key => $placeholder) {
            if (empty($placeholder)) {
                unset($placeholders[$key]);
            }
        }
        $placeholdersKeys = array_map(function($key) {
            return '%' . $key . '%';
        }, array_keys($placeholders));
        $placeholdersValues = array_values($placeholders);
        $elements = array();
        foreach ($this->locators as $key => $locator) {
            $locator[1] = str_replace($placeholdersKeys, $placeholdersValues, $locator[1]);
            /**#@+
             * Reflection crutch
             */
            $webDriverByReflection = new \ReflectionClass('Facebook\\WebDriver\\WebDriverBy');
            $webDriverBy = $webDriverByReflection->newInstanceWithoutConstructor();
            $constructor = $webDriverByReflection->getConstructor();
            $constructor->setAccessible(true);
            $constructor->invoke($webDriverBy, $locator[0], $locator[1]);
            /**#@-*/
            $elements[$key] = $webDriverBy;
        }
        return $elements;
    }
}
