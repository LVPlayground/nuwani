<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class BotGroup implements \ArrayAccess, \SeekableIterator, \Countable {
    /**
     * Defines a list of all bots included in this group, to be used with
     * method- and property forwarding.
     * 
     * @var array
     */
    private $m_aBotList;
    
    /**
     * In order to properly use this bot group, we really have to define the
     * bots which are included in this group. That's what this function is
     * for.
     * 
     * @param array $aBotList The bots we need to define.
     */
    public function __construct ($aBotList) {
        $this -> m_aBotList = $aBotList;
    }
    
    /**
     * Argument: sFunction (string) - Name of the function being called
     * Argument: aParameters (array) - Parameters being passed along
     *
     * Simply forward all method calls to all the bots in this group. Usage
     * is unknown, probably only for send and the like.
     * 
     * @param string $sFunction 
     * @param array $aParameters 
     */
    public function __call ($sFunction, $aParameters) {
        foreach ($this -> m_aBotList as $pBot)
        {
            if (is_callable (array ($pBot, $sFunction)))
                call_user_func_array (array ($pBot, $sFunction), $aParameters);
        }
    }
    
    /**
     * The count function returns the number of bots in this group, which
     * will have influence on the behaviour of count ().
     * 
     * @return integer
     */
    public function count () {
        return count ($this -> m_aBotList);
    }
    
    /**
     * This function is an implementation of the Iterator class'es function,
     * and will return the current, active element.
     * 
     * @return Bot
     */
    public function current () {
        return current ($this -> m_aBotList);
    }
    
    /**
     * This function is an implementation of the Iterator class'es function,
     * and will return the key of the current element, which is the nickname
     * of the currently selected in the bot list.
     * 
     * @return string
     */
    public function key () {
        $pBot = current ($this -> m_aBotList);
        if ($pBot instanceof Bot)
            return $pBot ['Nickname'];
        
        return key ($this -> m_aBotList);
    }
    
    /**
     * The seek function, used by the SeekableIterator interface, seeks to
     * a specific position in the bot-list array.
     * 
     * @param integer $iPosition Position to seek to.
     * @return Bot
     */
    public function seek ($iPosition) {
        reset ($this -> m_aBotList);
        for ($iCurrentPosition = 0; $iCurrentPosition < $iPosition; $iCurrentPosition ++)
        {
            if (next ($this -> m_aBotList) === false)
                return false ;
        }
        
        return current ($this -> m_aBotList);
    }
    
    /**
     * This function is an implementation of the Iterator class'es function,
     * and will return the next element in the array.
     * 
     * @return Bot
     */
    public function next () {
        return next ($this -> m_aBotList);
    }
    
    /**
     * This function is an implementation of the Iterator class'es function,
     * and will reset the entire array to the beginning.
     * 
     * @return Bot
     */
    public function rewind () {
        return reset ($this -> m_aBotList);
    }
    
    /**
     * This function is an implementation of the Iterator class'es function,
     * and will check whether the current index is valid.
     * 
     * @return boolean
     */
    public function valid () {
        return current ($this -> m_aBotList) !== false;
    }
    
    /**
     * A function which returns a certain key from the first bot in the
     * bot-group. No checking for existance is done here.
     * 
     * @param string $sKey Key of the entry that you want to receive.
     * @return mixed
     */
    public function offsetGet ($sKey) {
        if (count ($this -> m_aBotList))
        {
            $pTheBot = reset ($this -> m_aBotList) ;
            if ($pTheBot instanceof Bot)
            {
                return $pTheBot -> offsetGet ($sKey);
            }
        }
        
        return false ;
    }
    
    /**
     * Checks whether a key with this name exists, and if so, returns
     * true, otherwise a somewhat more negative boolean gets returned.
     * 
     * @param string $sKey Key of the entry that you want to check.
     * @return boolean
     */
    public function offsetExists ($sKey) {
        if (count ($this -> m_aBotList))
        {
            $pTheBot = reset ($this -> m_aBotList) ;
            if ($pTheBot instanceof Bot)
            {
                return $pTheBot -> offsetExists ($sKey);
            }
        }
        
        return false ;
    }
    
    /**
     * This function can be used to set associate a value with a certain key
     * in our internal array, however, that's disabled seeing we're locking
     * values.
     * 
     * @param string $sKey Key of the entry that you want to set.
     * @param mixed $mValue Value to assign to the key.
     * @return null
     */
    public function offsetSet ($sKey, $mValue) {
        return ;
    }
    
    /**
     * This function will get called as soon as unset() gets called on the 
     * Modules instance, which is not properly supported either.
     * 
     * @param string $sKey Key of the entry that you want to unset.
     * @return null
     */
    
    public function offsetUnset ($sKey) {
        return ;
    }
};
