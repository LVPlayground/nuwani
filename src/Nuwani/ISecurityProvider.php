<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

interface ISecurityProvider
{
    /**
     * These define a list of default provider levels that will be
     * implemented in their own module. Using them name-based looks better
     * than numberz. These have influence on the channel the command is 
     * being executed in.
     * 
     * @var integer
     */
    
    const   CHANNEL_ALL     = 0;
    const   CHANNEL_VOICE       = 1;
    const   CHANNEL_HALFOP      = 2;
    const   CHANNEL_OPERATOR    = 3;
    const   CHANNEL_PROTECTED   = 4;
    const   CHANNEL_OWNER       = 5;
    
    /**
     * Related to the evaluation class, this is a special constant. Only the
     * ones who are IDENTIFIED as the bot owner are allowed to execute this
     * command.
     * 
     * @var integer
     */
    
    const   BOT_OWNER       = 9999;
    
    /**
     * This function will check whether the security level as specified by
     * the second argument against the user the $pBot variable contains.
     * 
     * @param Bot $pBot The bot which we should check security against.
     * @param integer $nSecurityLevel Related level of security.
     * @return boolean
     */
    
    public function checkSecurity (Bot $pBot, $nSecurityLevel);
};
