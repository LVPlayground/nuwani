<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class Memory
{
	/**
	 * This property contains various statistics about our memory-cleanup
	 * operations. This utilizes a fairly new, not-documented PHP 5.3
	 * feature.
	 * 
	 * @var array
	 */
	
	private static $m_aStatistics;
	
	/**
	 * A property which defines the time the last garbage collection round
	 * took place, useful seeing we only want to do this once every five
	 * seconds.
	 * 
	 * @var integer
	 */
	
	private static $m_nLastCollect;
	
	/**
	 * In the constructor we'll simply initialise the earlier two properties,
	 * respectivily with an array and the current timestamp.
	 */
	
	public static function Initialise ()
	{
		self :: $m_aStatistics = array 
		(
			'Elements'	=> 0,
			'Memory'	=> 0,
			'Cycles'	=> 0
		);
		
		self :: $m_nLastCollect = time ();
		
		if (!gc_enabled ())
			gc_enable ();
	}
	
	/**
	 * The process function will determain whether we have to do a garbage
	 * collecting round, and if so, process the memory rounds.
	 */
	
	public static function Process ()
	{
		if ((time () - self :: $m_nLastCollect) >= 5)
		{
			$nStart = memory_get_usage ();
			self :: $m_aStatistics ['Elements'] += gc_collect_cycles ();
			
			$nDifference = (memory_get_usage () - $nStart);
			self :: $m_aStatistics ['Memory'] += ($nDifference > 0) ? $nDifference : 0;
			self :: $m_aStatistics ['Cycles'] ++;
			self :: $m_nLastCollect = time ();
		}
	}
	
	/**
	 * Returns the actual statistics of the memory class which'll handle
	 * garbage collecting for the script. Nice addition for stats.
	 * 
	 * @return array
	 */
	
	public static function getStatistics ()
	{
		return self :: $m_aStatistics; 
	}
}
