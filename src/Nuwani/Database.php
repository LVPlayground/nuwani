<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class Database extends \MySQLi
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
