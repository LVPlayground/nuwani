<?php
/**
 * Nuwani-v2 Bot Framework
 *
 * This file is part of the Nuwani v2 Bot Framework, a simple set of PHP classes
 * which allow you to set-up and run your own bot. It features advanced,
 * PHP 5.3 based syntax for optimal performance and security.
 *
 * @author Peter Beverloo <peter@lvp-media.com>
 */

namespace Nuwani;

class Database extends MySQLi
{
	/**
	 * This property contains the instance of the active MySQLi instance.
	 * By utilizing Singleton here we avoid having MySQL connections for
	 * every single requests, but rather just when they're needed.
	 * 
	 * @var string
	 */
	
	private static $m_sInstance;
	
	/**
	 * This property indicates when the current connection has to
	 * be killed, and restarted to clear up buffers and all.
	 * 
	 * @var integer
	 */
	
	private static $m_nRestartTime;
	
	/**
	 * Creates a new connection with the database or returns the active
	 * one if there is one, so no double connections for anyone.
	 * 
	 * @return Database
	 */
	
	public static function getInstance ()
	{
		if (self :: $m_sInstance == null || self :: $m_nRestartTime < time ())
		{
			self :: $m_sInstance = null; // close it
			
			$pConfiguration = Configuration :: getInstance ();
			$aConfiguration = $pConfiguration -> get ('MySQL');
			
			self :: $m_nRestartTime = $aConfiguration ['restart'] + time ();
			
			self :: $m_sInstance = new self
			(
				$aConfiguration ['hostname'],
				$aConfiguration ['username'],
				$aConfiguration ['password'],
				$aConfiguration ['database']
			);
		}
		
		return self :: $m_sInstance;
	}
};

?>