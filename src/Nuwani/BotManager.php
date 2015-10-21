<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class BotManager extends Singleton implements ArrayAccess, Countable
{
	/**
	 * Contains a list with all the bots loaded into the system. All bots
	 * are listed using their instance; other data is listed within.
	 * 
	 * @var array
	 */
	
	private $m_aBotList;
	
	/**
	 * A cache of the number of bots will be kept in this property. This
	 * way, we can check if we need to keep running the main loop or not,
	 * in a faster way than doing count() every loop.
	 * 
	 * @var integer
	 */
	
	private $m_nBots;
	
	/**
	 * In order to properly catch- and process shell signals coming from the
	 * Linux shell, we have to know whether the functions are avaialble.
	 * 
	 * @var boolean
	 */
	
	private $m_bDitpatchSignals;

	/**
	 * The centralized function which will properly initialise all initial
	 * bots to be initialised into the Nuwani Bot system.
	 * 
	 * @param array $aBotList The bots that have to be initialised
	 */
	
	public function Initialise ($aBotList)
	{
		$this -> m_nBots = 0;
		
		foreach ($aBotList as $aBotInfo)
		{
			$pBot = new Bot ($aBotInfo ['Nickname']);
			$pBot -> setNetwork ($aBotInfo ['Network']);
			
			if (isset ($aBotInfo ['BindIP']))
				$pBot ['Socket'] -> setBindTo ($aBotInfo ['BindIP']);
			
			if (isset ($aBotInfo ['SSL']))
				$pBot ['Socket'] -> setSecuredConnection ($aBotInfo ['SSL']);
			
			$pBot -> setBotInfo ($aBotInfo); 
			$pBot -> connect ();
		}
		
		if (function_exists ('pcntl_signal_dispatch'))
		{
			pcntl_signal (SIGTERM,  array ($this, 'onCatchSignal'));
			pcntl_signal (SIGINT,   array ($this, 'onCatchSignal'));
			pcntl_signal (SIGTERM,  array ($this, 'onCatchSignal'));
			
			$this -> m_bDitpatchSignals = true ;
		}
		else
		{
			$this -> m_bDitpatchSignals = false;
		}
	}

	/**
	 * The destructor will properly shut down all bots running under the
	 * Nuwani platform, in the order where they got started.
	 */
	
	public function __destruct ()
	{
		foreach ($this -> m_aBotList as $nBotIndex => $aBotInfo)
			$this -> unregister ($aBotInfo ['Instance']);
	}
	
	/**
	 * This function returns an array with all bot instances in the system,
	 * as a BotGroup. That way no dirty hacks have to be implemented in
	 * offsetGet().
	 * 
	 * @return BotGroup
	 */
	
	public function getBotList ()
	{
		$aBotList = array ();
		foreach ($this -> m_aBotList as $aBotInfo)
		{
			if ($aBotInfo ['Instance'] instanceof Bot)
			{
				$aBotList [] = $aBotInfo ['Instance'];
			}
		}
		
		return new BotGroup ($aBotList);
	}

	/**
	 * The register function will add a new bot to the internal lists,
	 * allowing it to be used by other systems (like IRC Echo for games).
	 * 
	 * @param Bot $pBot The bot that is going to be registered.
	 * @param string $sNickname A reference to the bot's current nickname.
	 */
	
	public function register (Bot $pBot, & $sNickname)
	{
		$this -> m_aBotList [] = array
		(
			'Instance' 	=> $pBot,
			'Started'	=> time (),
			'Nickname'	=> $sNickname
		);
		
		$this -> m_nBots ++;
	}
	
	/**
	 * After a bot shuts down, we have to be informed and remove it from the
	 * bot tracking array. It will no longer be used for whatever service
	 * possible.
	 * 
	 * @param Bot $pBot The bot that is going to be unregistered.
	 * @return boolean
	 */
	
	public function unregister (Bot $pBot)
	{
		foreach ($this -> m_aBotList as $nIndex => $pListedBot)
		{
			if ($pListedBot ['Instance'] != $pBot)
				continue ; // This is another bot
			
			$pBot -> destroy ();
			
			unset ($pListedBot, $pBot); // silly php refcount
			unset ($this -> m_aBotList [$nIndex]);
			
			$this -> m_nBots --;
			
			return true ;
		}
		
		return false ;
	}
	
	/**
	 * This function creates a bot with the specified name and network, and 
	 * optionally joins a number of channels.
	 * 
	 * @param string $sName Nickname of the bot to create.
	 * @param string $sNetwork Name of the network to connect with.
	 * @param array $aChannels Array of channels to auto-join.
	 */
	
	public function create ($sName, $sNetwork, $aChannels = array ())
	{
		$pBot = new Bot ($sName);
		$pBot -> setNetwork ($sNetwork);
		$pBot -> connect ();
		
		foreach ((array)$aChannels as $sChannel)
			$pBot -> send ('JOIN ' . $sChannel);
	}
	
	/**
	 * This function is an alias for unregister, seeing create- and destroy
	 * logically come together, and not create- and unregister. That'd be odd.
	 * 
	 * @param Bot $pBot The bot that you want to destroy.
	 */
	
	public function destroy (Bot $pBot)
	{
		$this -> unregister ($pBot);
	}
	
	/**
	 * The process function runs everythign for all bots, and because we are
	 * the only place where everything is known, we have to do so!
	 */
	
	public function process ()
	{
		foreach ($this -> m_aBotList as $iBotIndex => $pBot)
		{
			$pBot ['Instance'] -> process ();
		}
		
		if ($this -> m_bDitpatchSignals)
		{
			pcntl_signal_dispatch ();
		}
	}
	
	/**
	 * This function catches the signal thrown by the linux shell, when
	 * available. On some signals we want to initialise the shutdown process.
	 * 
	 * @param integer $nSignal The signal that has been received.
	 */
	
	public function onCatchSignal ($nSignal)
	{
		echo 'Nuwani is shutting down..' . NL;
		foreach ($this -> m_aBotList as $nBotIndex => $aBotInfo)
		{
			$this -> unregister ($aBotInfo ['Instance']);
		}
		
		// Initialise a waiting period so all socket operations can finish;
		for ($i = 0; $i < 4; $i ++)
			usleep (250000);
		
		echo 'Nuwani has been shutdown..' . NL;
		die ();
	}
	
	/**
	 * This method returns the number of bots in the BotManager. A bot will
	 * be destroyed as soon as it isn't usable anymore. That's the case when
	 * it can't connect at all, or has been shut down manually.
	 * 
	 * @return integer
	 */
	
	public function count ()
	{
		return $this -> m_nBots;
	}
	
	/**
	 * Returns a bot with the defined conditionals in place. First priority
	 * is checking for direct matches, otherwise patterns will be checked.
	 * 
	 * @param string $sKey Key of the entry that you want to receive.
	 * @return BotGroup|Bot
	 */
	
	public function offsetGet ($sKey)
	{
		$aChunks = explode (' ', strtolower ($sKey));
		$aMatch  = $aRequirements = array ();
		
		/** First decide the requirements for a bot to match **/
		foreach ($aChunks as $sChunk)
		{
			if (strpos ($sChunk, ':') !== false)
			{
				list ($sKey, $sValue) = explode (':', $sChunk, 2);
				if ($sKey == 'network' ||
				    $sKey == 'channel')
				{
					$aRequirements [ucfirst ($sKey)] = $sValue;
				}
				
				continue ;
			}
			
			if ($sChunk == 'master' ||
			    $sChunk == 'slave')
			{
				$aRequirements [ucfirst ($sChunk)] = true;
			}
		}
		
		/** And now see which bots match the requirements **/
		$bGotRequirements = count ($aRequirements) != 0;
		foreach ($this -> m_aBotList as $pBot)
		{
			// Renewed nickname matching;
			if ($pBot ['Nickname'] == $sKey)
			{
				$aMatch [] = & $pBot ['Instance'];
				continue ;
			}
			
			if (!$bGotRequirements)
				continue ; // no requirements; name matching
			
			if (isset ($aRequirements ['Network']) && 
			    strtolower ($pBot ['Instance']['Network']) != $aRequirements ['Network'])
			{
				continue ;
			}
			
			if (isset ($aRequirements ['Channel']) && !$pBot ['Instance'] -> inChannel ($aRequirements ['Channel']))
				continue ; // Not in the required channel;
			
			if (isset ($aRequirements ['Slave']) && $pBot ['Instance']['Slave'] == false)
				continue ; // Not a slave; we want a slave.
			
			if (isset ($aRequirements ['Master']) && $pBot ['Instance']['Slave'] == true)
				continue ; // We want a master, but this is a slave.
			
			$aMatch [] = & $pBot ['Instance'];
		}
		
		if (count ($aMatch) == 1) // No need for a group if we're alone
			return array_pop ($aMatch);
		
		return new BotGroup ($aMatch);
	}
	
	/**
	 * Disabled due to the advanced nature of offsetGet, you should ALWAYS
	 * check for the returning value instead of assuming there is any.
	 * 
	 * @param string $sKey Key of the entry that you want to check.
	 * @return boolean
	 */
	
	public function offsetExists ($sKey)
	{
		return false ;
	}
	
	/**
	 * Creating new bots should be done with the register function, therefore
	 * this function is NOT valid and will simply return null.
	 * 
	 * @param string $sKey Key of the entry that you want to set.
	 * @param mixed $mValue Value to assign to the key.
	 */
	
	public function offsetSet ($sKey, $mValue)
	{
		return ;
	}
	
	/**
	 * Unsetting bots is something that should be done using the unregister
	 * function, not by us, so therefore we simply return null.
	 * 
	 * @param string $sKey Key of the entry that you want to unset.
	 */
	
	public function offsetUnset ($sKey)
	{
		return ;
	}
};
