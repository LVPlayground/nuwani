<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class Timer {
    /**
     * This property contains all the timers which currently are running.
     * The per-timer array contains the start- and endtime and the callback.
     * @var array
     */
    private static $m_aRunningTimers = [];

    /**
     * An alternative syntax for the Create function, so you can use
     * new Timer (..) instead of calling the Create function.
     *
     * @param callback $aFunction  Function you wish to call.
     * @param integer  $nInterval  Ideal interval in miliseconds, won't be accurate.
     * @param boolean  $bRepeating Repeat this timer forever or stop it after?
     */
    public function __construct($aFunction, $nInterval = 1000, $bRepeating = false) {
        self::Create($aFunction, $nInterval, $bRepeating);
    }

    /**
     * This function creates a new timer using the arguments as passed on
     * to the function. It runs using the Process function.
     *
     * @param callback $aFunction  Function you wish to call.
     * @param integer  $nInterval  Ideal interval in miliseconds, won't be accurate.
     * @param boolean  $bRepeating Repeat this timer forever or stop it after?
     *
     * @return string
     */
    public static function Create($aFunction, $nInterval = 1000, $bRepeating = false) {
        $sTimerId = substr(sha1(time() . '-' . uniqid()), 5, 10);
        $nInterval /= 1000;

        if(!is_callable($aFunction)) {
            throw new \Exception ('Could not create the timer due to the function not being callable.');

            return false;
        }

        self::$m_aRunningTimers [] = [
            'TimerId' => $sTimerId,
            'Function' => $aFunction,
            'Interval' => $nInterval,
            'Repeating' => $bRepeating,
            'RunAt' => microtime(true) + $nInterval
        ];

        return $sTimerId;
    }

    /**
     * This function will stop running a certain timer given the timerID as
     * passed on as the first argument of this function in a very long
     * sentence.
     *
     * @param string $sTimerId Timer ID that you wish to stop.
     *
     * @return boolean
     */
    public static function Stop($sTimerId) {
        foreach(self::$m_aRunningTimers as $iTimerIndex => $aTimerInfo) {
            if($aTimerInfo ['TimerId'] == $sTimerId) {
                unset (self::$m_aRunningTimers [$iTimerIndex], $aTimerInfo);

                return true;
            }
        }

        return false;
    }

    /**
     * The process function will check all the timers to see whether they
     * have to be executed, and if so, execute them accordingly.
     */
    public static function process() {
        $nCurrentTime = microtime(true);
        foreach(self::$m_aRunningTimers as $iTimerIndex => & $aTimerInfo) {
            if($aTimerInfo ['RunAt'] < $nCurrentTime) {
                try {
                    call_user_func($aTimerInfo ['Function']);
                } catch(\Exception $pException) {
                    ErrorExceptionHandler::getInstance()->processException($pException);
                    @ ob_end_flush();
                }

                if($aTimerInfo ['Repeating']) {
                    $aTimerInfo ['RunAt'] = microtime(true) + $aTimerInfo ['Interval'];
                } else {
                    unset (self::$m_aRunningTimers [$iTimerIndex]);
                }
            }
        }
    }

    /**
     * This function can be used to get a list of all the timers which
     * currently are active in the system. The first argument can be used
     * to determain whether to filter the list and include only permanent
     * timers.
     *
     * @param boolean $bOnlyPermanent Only permanent, repeating timers?
     *
     * @return array
     */
    public static function getTimerList($bOnlyPermanent = false) {
        if($bOnlyPermanent) {
            $aTimers = [];
            foreach(self::$m_aRunningTimers as $aTimerInfo) {
                if($aTimerInfo ['Repeating'] !== true) {
                    continue;
                }

                $aTimers [] = $aTimerInfo;
            }

            return $aTimers;
        }

        return self::$m_aRunningTimers;
    }

    /**
     * This function will return a certain option from one of the timers
     * that are controlled using this class. They can be changed using
     * setTimerOption.
     *
     * @param string $sTimerId Timer ID to get the option of.
     * @param string $sOption  Which option to retrieve from this timer?
     *
     * @return mixed
     */
    public static function getTimerOption($sTimerId, $sOption) {
        foreach(self::$m_aRunningTimers as $iTimerIndex => $aTimerInfo) {
            if($aTimerInfo ['TimerId'] == $sTimerId) {
                if(!isset($aTimerInfo [$sOption])) {
                    throw new \Exception ('There is no option "' . $sOption . '" for timers.');

                    return false;
                }

                return $aTimerInfo [$sOption];
            }
        }

        throw new \Exception ('No timer could be found with the ID "' . $sTimerId . '".');

        return false;
    }

    /**
     * A fairly easy function which will change a setting related to one of
     * the timers in the system. Value should be valid, doesn't get checked
     * in here.
     *
     * @param string $sTimerId Timer that you wish to change.
     * @param string $sOption  The option that's about to be changed.
     * @param mixed  $mValue   Value to give to the option.
     *
     * @return boolean
     */
    public static function setTimerOption($sTimerId, $sOption, $mValue) {
        foreach(self::$m_aRunningTimers as $iTimerIndex => $aTimerInfo) {
            if($aTimerInfo ['TimerId'] == $sTimerId) {
                if(!isset($aTimerInfo [$sOption])) {
                    throw new \Exception ('There is no option "' . $sOption . '" for timers.');

                    return false;
                }

                $aTimerInfo [$sOption] = $mValue;

                return true;
            }
        }

        throw new \Exception ('No timer could be found with the ID "' . $sTimerId . '".');

        return false;
    }
}
