<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2005 Sebastian Kurfuerst (sebastian@garbage-group.de)
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Contains the update class for the compatibility version. Used by the update wizard in the install tool.
 *
 * @author	Sebastian Kurfuerst <sebastian@garbage-group.de
 */

class tx_coreupdates_compatversion {
	var $versionNumber;	// version number coming from t3lib_div::int_from_ver()
	var $pObj;	// parent object (tx_install)
	var $userInput;	// user input

	function checkForUpdate($description)	{
		global $TYPO3_CONF_VARS;
		if ($this->compatVersionIsCurrent())	{
			$description = '<b>If you do not use the wizard, your current TYPO3 installation is configured to use all the features included in the current release '.TYPO3_version.'.</b>
			There are two possibilities that you see this screen:<ol><li><b>You just updated from a previous version of TYPO3:</b>
			Because of some new features, the frontend output of your site might have changed. To emulate the "old" frontend behavior, change the compatibility version by continuing to step 2.
			This is <b>recommended</b> after every update to make sure the frontend output is not altered. When re-running the wizard, you will see the changes needed for using the new features.
			<i>Please continue to step two.</i></li>
			<li><b>You just made a fresh install of TYPO3:</b>
			Perfect! All new features will be used.
			<i>You can stop here and do not need this wizard now.</i></li></ol>';

			if (!$TYPO3_CONF_VARS['SYS']['compat_version'])	{
				$description .= '
				The compatibility version has been set to the current TYPO3 version. This is a stamp and has no impact for your installation.';
			}
		} else {
			$description = 'Your current TYPO3 installation is configured to <b>behave like version '.$TYPO3_CONF_VARS['SYS']['compat_version'].'</b> of TYPO3. If you just upgraded from this version, you most likely want to <b>use new features</b> as well. In the next step, you will see the things that need to be adjusted to make your installation compatible with the new features.';
		}

		return 1;
	}

	function getUserInput($inputPrefix)	{
		global $TYPO3_CONF_VARS;
		if ($this->compatVersionIsCurrent())	{
			$content = '<b>You updated from an older version of TYPO3</b>:<br>
			<label for="'.$inputPrefix.'[version]">Select the version where you have upgraded from:</label> <select name="'.$inputPrefix.'[version]" id="'.$inputPrefix.'[version]">';
			$versions = array(
				'3.8.1' => '<= 3.8.1'
			);
			foreach ($versions as $singleVersion => $caption)	{
				$content .= '<option value="'.$singleVersion.'">'.$caption.'</option>';
			}
			$content .= '</select>';
		} else {
			$content = 'TYPO3 output is currently compatible to version '.$TYPO3_CONF_VARS['SYS']['compat_version'].'. To use all the new features in the current TYPO3 version, make sure you follow the guidelines below to upgrade without problems.<br />
			<b>Follow the steps below carefully and confirm every step!</b> You will see this list again after you performed the update.';

			$content .= $this->showChangesNeeded($inputPrefix);

			$content .= '<br /><input type="checkbox" name="'.$inputPrefix.'[compatVersion][all]" id="'.$inputPrefix.'[compatVersion][all]" value="1"> <b><label for="'.$inputPrefix.'[compatVersion][all]">ignore selection above - WARNING: this might break the output of your website.</label></b>';
		}
		return $content;
	}
	function checkUserInput($customMessages)	{
		global $TYPO3_CONF_VARS;
		if ($this->compatVersionIsCurrent())	{
			return 1;
		} else {
			if ($this->userInput['compatVersion']['all'])	{
				return 1;
			} else {
				$performUpdate = 1;
				$oldVersion = t3lib_div::int_from_ver($TYPO3_CONF_VARS['SYS']['compat_version']);
				$currentVersion = t3lib_div::int_from_ver(TYPO3_version);
				foreach ($TYPO3_CONF_VARS['SC_OPTIONS']['ext/install']['compat_version'] as $internalName => $details)	{
					if ($details['version'] > $oldVersion && $details['version'] <= $currentVersion)	{
						if (!$this->userInput['compatVersion'][$internalName])	{
							$performUpdate = 0;
							$customMessages = 'If you want to update the compatibility version, you need to confirm all checkboxes on the previous page.';
							break;
						}
					}
				}
				return $performUpdate;
			}
		}
	}
	function performUpdate($dbQueries, $customMessages)	{
		$customMessages = '';

			// if we just set it to an older version
		if ($this->userInput['version'])	{
			 $customMessages .= 'If you want to see what you need to do to use the new features, run the update wizard again!';
		}

		$linesArr = $this->pObj->writeToLocalconf_control();
		$version = $this->userInput['version']?$this->userInput['version']:TYPO3_version;
		$this->pObj->setValueInLocalconfFile($linesArr, '$TYPO3_CONF_VARS["SYS"]["compat_version"]', $version);
		$this->pObj->writeToLocalconf_control($linesArr,0);
		$customMessages .= '
		The compatibility version has been set to '.$version.'.';
		$customMessages .= $this->showChangesNeeded();

		return 1;
	}

		// helper functiopns
	function compatVersionIsCurrent()	{
		global $TYPO3_CONF_VARS;
		if ($TYPO3_CONF_VARS['SYS']['compat_version'] && t3lib_div::int_from_ver(TYPO3_version) != t3lib_div::int_from_ver($TYPO3_CONF_VARS['SYS']['compat_version']))	{
			return 0;
		} else {
			return 1;
		}
	}
	function showChangesNeeded($inputPrefix = '')	{
		global $TYPO3_CONF_VARS;
		$oldVersion = t3lib_div::int_from_ver($TYPO3_CONF_VARS['SYS']['compat_version']);
		$currentVersion = t3lib_div::int_from_ver(TYPO3_version);

		$tableContents = '';
		foreach ($TYPO3_CONF_VARS['SC_OPTIONS']['ext/install']['compat_version'] as $internalName => $details)	{
			if ($details['version'] > $oldVersion && $details['version'] <= $currentVersion)	{
				$tableContents .= '<tr><td colspan="2"><hr /></td></tr>
				<tr><td valign="bottom">'.($inputPrefix?'<input type="checkbox" name="'.$inputPrefix.'[compatVersion]['.$internalName.']" id="'.$inputPrefix.'[compatVersion]['.$internalName.']" value="1">':'&nbsp;').'</td><td>'.str_replace(chr(10),'<br />',$details['description']).($inputPrefix?'<br /><b><label for="'.$inputPrefix.'[compatVersion]['.$internalName.']">'.$details['description_acknowledge'].'</label></b>':'').'</td></tr>';
			}
		}
		if ($tableContents)	{
			return '<table>'.$tableContents.'</table>';
		}
	}
}
?>