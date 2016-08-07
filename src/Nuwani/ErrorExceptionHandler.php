<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class ErrorExceptionHandler extends Singleton {
    /**
     * The following constants define various levels of output for errors
     * and exception messages, mainly used for when they occur (duh).
     * @var integer
     */
    const ERROR_OUTPUT_SILENT = 1;
    const ERROR_OUTPUT_NORMAL = 2;
    const ERROR_OUTPUT_ALL = 3;

    /**
     * Defines the way this handler should output the error- and exception
     * messages. Hide all, show everything or show just small bits?
     * @var integer
     */
    private $m_nHandlerLevel;

    /**
     * A public, static variable which indicates the bot that's the current
     * context we have to run in. The error will be send to the modules
     * with the very bot that caused it.
     * @var Bot
     */
    public static $Context;

    /**
     * In order to properly handle exceptions and return them to the person/
     * function that threw them, we want to know the source of the exception.
     * @var string
     */
    public static $Source;

    /**
     * The constructor initialises the class, mainly by setting the default
     * error handler level to "normal", which seems logical.
     */
    protected function __construct() {
        $this->m_nHandlerLevel = self ::ERROR_OUTPUT_NORMAL;

        set_error_handler([$this, 'processError']);
        // Don't uncomment this, since Exceptions will be fatal when you do.
        //set_exception_handler    (array ($this, 'processException'));
    }

    /**
     * This method will be used to initialise the ErrorException handler
     * when the bot starts up.
     *
     * @param integer $nHandlerLevel Handler level we should be following.
     */
    public function Initialise($nHandlerLevel) {
        $this->setHandlerLevel($nHandlerLevel);
    }

    /**
     * Sets the errorhandling level. All errors that occur outside the setup
     * handlerlevel, will not be shown.
     *
     * @param integer $nHandlerLevel Handler level we should be following.
     */
    public function setHandlerLevel($nHandlerLevel) {
        if($nHandlerLevel < self ::ERROR_OUTPUT_SILENT || $nHandlerLevel > self ::ERROR_OUTPUT_ALL) {
            return;
        }

        $this->m_nHandlerLevel = $nHandlerLevel;
    }

    /**
     * A handler for error which occur during the execution of the Nuwani
     * IRC platform. All errors which can be handled (so no fatal's) get
     * passed through here. Return true to indicate that the error has been
     * handled and that PHP doesn't have to do anything anymore.
     *
     * @param integer $nErrorType   Type of error that has occured, like a warning.
     * @param string  $sErrorString A textual representation of the error.
     * @param string  $sErrorFile   File in which the error occured
     * @param integer $nErrorLine   On which line did the error occur?
     *
     * @return true
     */
    public function processError($nErrorType, $sErrorString, $sErrorFile, $nErrorLine) {
        if(error_reporting() == 0) // Make the @-operator work once again;
        {
            return true;
        }

        $nHandleErrors = E_DEPRECATED | E_USER_DEPRECATED;
        switch($this->m_nHandlerLevel) {
            case self ::ERROR_OUTPUT_ALL:
                $nHandleErrors |= E_NOTICE;
                $nHandleErrors |= E_USER_NOTICE;
            /** Deliberate fall-through */

            case self ::ERROR_OUTPUT_NORMAL:
                $nHandleErrors |= E_WARNING;
                $nHandleErrors |= E_USER_WARNING;
                break;
        }

        if(!($nHandleErrors & $nErrorType)) {
            return true;
        }
        /** Ignore it **/

        if(self::$Context != null && self::$Context instanceof Bot) {
            ModuleManager::getInstance()->onError(self::$Context, $nErrorType, $sErrorString, $sErrorFile, $nErrorLine);

            return true;
        }

        switch($nErrorType) {
            case E_WARNING:
                echo '[Warning]';
                break;
            case E_USER_WARNING:
                echo '[Warning]';
                break;
            case E_NOTICE:
                echo '[Notice]';
                break;
            case E_USER_NOTICE:
                echo '[Notice]';
                break;
            case E_DEPRECATED:
                echo '[Deprecated]';
                break;
            case E_USER_DEPRECATED:
                echo '[Deprecated]';
                break;
        }

        echo ' Error occured in "' . $sErrorFile . '" on line ' . $nErrorLine . ': "';
        echo $sErrorString . '".' . PHP_EOL;

        return true;
    }

    /**
     * Of course, even exceptions shouldn't be fatal for the bot's run-time.
     * Therefore we want to catch exceptions which occur as well.
     *
     * @param \Exception $pException The exception that has occured.
     *
     * @return true
     */
    public function processException(\Exception $pException) {
        if(self::$Context != null && self::$Context instanceof Bot) {
            $sExceptionSource = self::$Source;

            ModuleManager::getInstance()->onException(self::$Context, $sExceptionSource, $pException);
            self::$Source = null;

            return true;
        }

        echo '[Exception] Exception occured in "' . $pException->getFile() . '" on line ';
        echo $pException->getLine() . ': "' . $pException->getMessage() . '".' . PHP_EOL;

        return true;
    }
}
