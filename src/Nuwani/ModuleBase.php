<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

abstract class ModuleBase
{
	/**
	 * These constants can be used to cleanly format IRC messages without
	 * the need to use them in-line. There are various COLOUR_* constants
	 * too. In octal by the way.
	 * 
	 * @var string
	 */
	
	const	BOLD			= "\002";
	const	CLEAR			= "\017";
	const	COLOUR			= "\003";
	const	CTCP			= "\001";
	const	INVERSE			= "\026";
	const	TAB			= "\011";
        const   ITALIC                  = "\035";
	const	UNDERLINE		= "\037";
	
	/**
	 * A set of constant values which define the colours that can be used
	 * with IRC messages. Keep in mind that these do not include backgrounds.
	 * 
	 * @var string
	 */
	
	const	COLOUR_WHITE		= "\00300";
	const	COLOUR_BLACK		= "\00301";
	const	COLOUR_DARKBLUE		= "\00302";
	const	COLOUR_DARKGREEN	= "\00303";
	const	COLOUR_RED		= "\00304";
	const	COLOUR_BROWN		= "\00305";
	const	COLOUR_PURPLE		= "\00306";
	const	COLOUR_ORANGE		= "\00307";
	const	COLOUR_YELLOW		= "\00308";
	const	COLOUR_GREEN		= "\00309";
	const	COLOUR_TEAL		= "\00310";
	const	COLOUR_LIGHTBLUE	= "\00311";
	const	COLOUR_BLUE		= "\00312";
	const	COLOUR_PINK		= "\00313";
	const	COLOUR_DRAKGREY		= "\00314";
	const	COLOUR_GREY		= "\00315";
	
}

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
	
	const	CHANNEL_ALL		= 0;
	const	CHANNEL_VOICE		= 1;
	const	CHANNEL_HALFOP		= 2;
	const	CHANNEL_OPERATOR	= 3;
	const	CHANNEL_PROTECTED	= 4;
	const	CHANNEL_OWNER		= 5;
	
	/**
	 * Related to the evaluation class, this is a special constant. Only the
	 * ones who are IDENTIFIED as the bot owner are allowed to execute this
	 * command.
	 * 
	 * @var integer
	 */
	
	const	BOT_OWNER		= 9999;
	
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

interface ISecurityModule
{
	/**
	 * This function will register a new security provider with this very
	 * module, as soon as it gets available. Will be called automatically.
	 * 
	 * @param ISecurityProvider The security provider to register with this module.
	 * @param integer $nLevel Security level this provider will register.
	 */
	
	public function registerSecurityProvider (ISecurityProvider $pProvider, $nLevel);
	
	/**
	 * Will be called when a security provider unloads, so we will be aware
	 * that we won't be able to use it anymore. Could be for any reason.
	 * 
	 * @param ISecurityProvider $pProvider Security Provider that is being unloaded.
	 */
	
	public function unregisterSecurityProvider (ISecurityProvider $pProvider);
}
