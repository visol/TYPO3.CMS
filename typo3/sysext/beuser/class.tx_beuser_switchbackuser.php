<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Backend user switchback
 *
 * @author	Sebastian Kurfuerst <sebastian@garbage-group.de>
 */

class tx_beuser_switchbackuser {
	function switchBack($params, $that)	{
		if($that->session_table == 'be_sessions' && $that->user['uid'] && $that->user['ses_backuserid']) {
			$updateData = array(
				'ses_userid' => $that->user['ses_backuserid'],
				'ses_backuserid' => 0
				);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('be_sessions', 'ses_id='.$GLOBALS['TYPO3_DB']->fullQuoteStr($GLOBALS['BE_USER']->id, 'be_sessions').' AND ses_name=\'be_typo_user\' AND ses_userid='.intval($GLOBALS['BE_USER']->user['uid']),$updateData);

			header('Location: '.t3lib_div::locationHeaderUrl($GLOBALS['BACK_PATH'].'index.php'.($GLOBALS['TYPO3_CONF_VARS']['BE']['interfaces']?'':'?commandLI=1')));
			exit;
			//$GLOBALS['TYPO4
		}
	}
}

?>