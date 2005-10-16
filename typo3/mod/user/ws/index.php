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
 * Module: Workspace manager
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *  103: class SC_mod_user_ws_index extends t3lib_SCbase
 *
 *              SECTION: Standard module initialization
 *  136:     function menuConfig()
 *  181:     function init()
 *  236:     function main()
 *  268:     function printContent()
 *
 *              SECTION: Module content: Publish
 *  298:     function moduleContent_publish()
 *  381:     function displayVersionDetails($details)
 *  390:     function displayWorkspaceOverview()
 *  454:     function displayWorkspaceOverview_list($pArray, $tableRows=array(), $c=0)
 *  604:     function displayWorkspaceOverview_pageTreeIconTitle($pageUid, $title, $indentCount)
 *  621:     function displayWorkspaceOverview_commandLinks($table,&$rec_on,&$rec_off)
 *  654:     function displayWorkspaceOverview_setInPageArray(&$pArray,$rlArr,$table,$row)
 *  683:     function subElements($uid,$treeLevel,$origId=0)
 *  785:     function subElements_getNonPageRecords($tN, $uid, &$recList)
 *  814:     function subElements_renderItem(&$tCell,$tN,$uid,$rec,$origId,$iconMode,$HTMLdata)
 *  880:     function markupNewOriginals()
 *  902:     function createDiffView($table, $diff_1_record, $diff_2_record)
 *
 *              SECTION: Module content: Workspace list
 *  995:     function moduleContent_workspaceList()
 *
 *              SECTION: Helper functions
 * 1043:     function formatVerId($verId)
 * 1053:     function formatWorkspace($wsid)
 * 1080:     function formatCount($count)
 *
 *              SECTION: Processing
 * 1121:     function publishAction()
 *
 * TOTAL FUNCTIONS: 21
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */


	// Initialize module:
unset($MCONF);
require ('conf.php');
require ($BACK_PATH.'init.php');
require ($BACK_PATH.'template.php');
$BE_USER->modAccess($MCONF,1);

	// Include libraries of various kinds used inside:
$LANG->includeLLFile('EXT:lang/locallang_mod_user_ws.xml');
require_once(PATH_t3lib.'class.t3lib_scbase.php');
require_once(PATH_typo3.'mod/user/ws/class.wslib.php');
require_once(PATH_t3lib.'class.t3lib_diff.php');
require_once(PATH_t3lib.'class.t3lib_pagetree.php');
require_once (PATH_t3lib.'class.t3lib_tcemain.php');




/**
 * Module: Workspace manager
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage core
 */
class SC_mod_user_ws_index extends t3lib_SCbase {

		// Static:
	var $pageTreeIndent = 8;
	var $pageTreeIndent_titleLgd = 30;

		// Default variables for backend modules
	var $MCONF = array();				// Module configuration
	var $MOD_MENU = array();			// Module menu items
	var $MOD_SETTINGS = array();		// Module session settings
	var $doc;							// Document Template Object
	var $content;						// Accumulated content


		// Internal:
	var $showWorkspaceCol = 0;
	var $formatWorkspace_cache = array();
	var $formatCount_cache = array();
	var $targets = array();		// Accumulation of online targets.



	/*********************************
	 *
	 * Standard module initialization
	 *
	 *********************************/

	/**
	 * Initialize menu configuration
	 *
	 * @return	void
	 */
	function menuConfig()	{

			// Menu items:
		$this->MOD_MENU = array(
			'function' => array(
				'publish' => 'Review and Publish',
				'workspaces' => 'Workspace list',
			),
			'filter' => array(
				1 => 'Drafts',
				2 => 'Archive',
				0 => 'All',
			),
			'display' => array(
				0 => '[ONLINE]',
				-98 => 'Workspaces',
				-99 => 'All',
				-1 => '[Offline]'
			),
			'diff' => array(
				0 => 'No diff.',
				1 => 'Show diff. inline',
				2 => 'Show diff. popups',
			),
			'expandSubElements' => '',
		);

			// Add workspaces:
		if ($GLOBALS['BE_USER']->workspace===0)	{	// Spend time on this only in online workspace because it might take time:
			$workspaces = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title,adminusers,members,reviewers','sys_workspace','pid=0'.t3lib_BEfunc::deleteClause('sys_workspace'),'','title');
			foreach($workspaces as $rec)	{
				if ($GLOBALS['BE_USER']->checkWorkspace($rec))	{
					$this->MOD_MENU['display'][$rec['uid']] = '['.$rec['uid'].'] '.$rec['title'];
				}
			}
		}

			// CLEANSE SETTINGS
		$this->MOD_SETTINGS = t3lib_BEfunc::getModuleData($this->MOD_MENU, t3lib_div::_GP('SET'), $this->MCONF['name'], 'ses');
	}

	/**
	 * Standard init function of a module.
	 *
	 * @return	void
	 */
	function init()	{
		global $BACK_PATH;

			// Setting module configuration:
		$this->MCONF = $GLOBALS['MCONF'];

			// Initialize Document Template object:
		$this->doc = t3lib_div::makeInstance('noDoc');
		$this->doc->backPath = $BACK_PATH;
		$this->doc->docType = 'xhtml_trans';

				// JavaScript
		$this->doc->JScode = $this->doc->wrapScriptTags('
			script_ended = 0;
			function jumpToUrl(URL)	{	//
				document.location = URL;
			}

			function hlSubelements(origId, verId, over, diffLayer)	{	//
				if (over)	{
					document.getElementById(\'orig_\'+origId).attributes.getNamedItem("class").nodeValue = \'typo3-ver-hl\';
					document.getElementById(\'ver_\'+verId).attributes.getNamedItem("class").nodeValue = \'typo3-ver-hl\';
					if (diffLayer)	{
						document.getElementById(\'diff_\'+verId).style.visibility = \'visible\';
					}
				} else {
					document.getElementById(\'orig_\'+origId).attributes.getNamedItem("class").nodeValue = \'typo3-ver\';
					document.getElementById(\'ver_\'+verId).attributes.getNamedItem("class").nodeValue = \'typo3-ver\';
					if (diffLayer)	{
						document.getElementById(\'diff_\'+verId).style.visibility = \'hidden\';
					}
				}
			}

		');
		$this->doc->form = '<form action="index.php" method="post" name="pageform">';

			// Setting up the context sensitive menu:
		$CMparts = $this->doc->getContextMenuCode();
		$this->doc->JScode.= $CMparts[0];
		$this->doc->bodyTagAdditions = $CMparts[1];
		$this->doc->postCode.= $CMparts[2];

			// Add JS for dynamic tabs:
		$this->doc->JScode.= $this->doc->getDynTabMenuJScode();

			// Parent initialization:
		parent::init();
	}

	/**
	 * Main function for Workspace Manager module.
	 *
	 * @return	void
	 */
	function main()	{
		global $LANG;

			// Perform workspace publishing action if buttons are pressed:
		$this->publishAction();

			// Starting page:
		$this->content.=$this->doc->startPage($LANG->getLL('title'));
		$this->content.=$this->doc->header($LANG->getLL('title'));
		$this->content.=$this->doc->spacer(5);

			// Build top menu:
		$menuItems = array();
		$menuItems[] = array(
			'label' => $LANG->getLL('menuitem_review'),
			'content' => $this->moduleContent_publish()
		);
		$menuItems[] = array(
			'label' => $LANG->getLL('menuitem_workspaces'),
			'content' => $this->moduleContent_workspaceList()
		);

			// Add hidden fields and create tabs:
		$content = $this->doc->getDynTabMenu($menuItems,'user_ws');
		$this->content.=$this->doc->section('',$content,0,1);
	}

	/**
	 * Print module content. Called as last thing in the global scope.
	 *
	 * @return	void
	 */
	function printContent()	{
		global $SOBE;

		$this->content.= $this->doc->endPage();
		echo $this->content;
	}













	/*********************************
	 *
	 * Module content: Publish
	 *
	 *********************************/

	/**
	 * Rendering the content for the publish and review panel in the workspace manager
	 *
	 * @return	string		HTML content
	 */
	function moduleContent_publish()	{

			// Initialize:
		$content = '';
		$details = t3lib_div::_GP('details');

			// Create additional menus:
		$menu = '';
		if ($GLOBALS['BE_USER']->workspace===0)	{
			$menu.= t3lib_BEfunc::getFuncMenu(0,'SET[filter]',$this->MOD_SETTINGS['filter'],$this->MOD_MENU['filter']);
			$menu.= t3lib_BEfunc::getFuncMenu(0,'SET[display]',$this->MOD_SETTINGS['display'],$this->MOD_MENU['display']);
		} else {
			$menu.= t3lib_BEfunc::getFuncMenu(0,'SET[diff]',$this->MOD_SETTINGS['diff'],$this->MOD_MENU['diff']);
			$menu.= t3lib_BEfunc::getFuncCheck(0,'SET[expandSubElements]',$this->MOD_SETTINGS['expandSubElements']).' Show sub-elements - ';
		}

			// Create header:
		$title = '';
		$description = '';
		switch($GLOBALS['BE_USER']->workspace)	{
			case 0:
				$title = t3lib_iconWorks::getIconImage('sys_workspace', array(), $this->doc->backPath, ' align="top"').'[ONLINE workspace]';
				$description = 'The ONLINE workspace allows changes to take effect immediately on the website. However versioning can be applied inside the online workspace and this overview shows you draft and archive versions from the various workspaces, including the ONLINE workspace.';
			break;
			case -1:
				$title = t3lib_iconWorks::getIconImage('sys_workspace', array(), $this->doc->backPath, ' align="top"').'[Offline workspace]';
				$description = 'In the Offline workspace all changes will be made as new versions of the online content. Changes can be previewed, reviewed and eventually published online.';
			break;
			case -99:
				$title = $this->doc->icons(3).'[NONE]';
				$description = 'You have no access to any workspace which might be an error. Please contact you administrator!';
			break;
			default:
				$title = t3lib_iconWorks::getIconImage('sys_workspace', $GLOBALS['BE_USER']->workspaceRec, $this->doc->backPath, ' align="top"').
							'['.$GLOBALS['BE_USER']->workspace.'] '.t3lib_BEfunc::getRecordTitle('sys_workspace',$GLOBALS['BE_USER']->workspaceRec,TRUE);
				$description = ($GLOBALS['BE_USER']->workspaceRec['description'] ? htmlspecialchars($GLOBALS['BE_USER']->workspaceRec['description']) : '<em>[None]</em>');
			break;
		}

			// Buttons for publish / swap:
		$actionLinks = '';
		if ($GLOBALS['BE_USER']->workspace!==0)	{
			$actionLinks.= '<input type="submit" name="_publish" value="Publish workspace" onclick="return confirm(\'Are you sure you want to publish all content of the workspace?\');"/>';
			$actionLinks.= '<input type="submit" name="_swap" value="Swap workspace" onclick="return confirm(\'Are you sure you want to publish (swap) all content of the workspace?\');" />';
		}

			// Add header to content variable:
		$content = '
		<table border="0" cellpadding="1" cellspacing="1" class="lrPadding" style="border: 1px solid black;">
			<tr>
				<td class="bgColor2" nowrap="nowrap"><b>Workspace:</b>&nbsp;</td>
				<td class="bgColor4" nowrap="nowrap">'.$title.'</td>
			</tr>
			<tr>
				<td class="bgColor2" nowrap="nowrap"><b>Description:</b>&nbsp;</td>
				<td class="bgColor4">'.$description.'</td>
			</tr>'.($GLOBALS['BE_USER']->workspace!=-99 && !$details ? '
			<tr>
				<td class="bgColor2" nowrap="nowrap"><b>Options:</b>&nbsp;</td>
				<td class="bgColor4">'.$menu.$actionLinks.'</td>
			</tr>' : '').'
		</table>
		<br/>
		';

			// Add publishing and review overview:
		if ($GLOBALS['BE_USER']->workspace!=-99)	{
			if ($details)	{
				$content.= $this->displayVersionDetails($details);
			} else {
				$content.= $this->displayWorkspaceOverview();
			}
			$content.='<br/>';
		}

			// Return content:
		return $content;
	}

	/**
	 * Display details for a single version from workspace
	 *
	 * @param	string		Version identification, made of table and uid
	 * @return	string		HTML string
	 */
	function displayVersionDetails($details)	{
		return 'TODO: Show details for version "'.$details.'"<hr/><a href="index.php">BACK</a>';
	}

	/**
	 * Rendering the overview of versions in the current workspace
	 *
	 * @return	string		HTML (table)
	 */
	function displayWorkspaceOverview()	{

			// Initialize variables:
		$this->showWorkspaceCol = $GLOBALS['BE_USER']->workspace===0 && $this->MOD_SETTINGS['display']<=-98;

			// Initialize Workspace ID and filter-value:
		if ($GLOBALS['BE_USER']->workspace===0)	{
			$wsid = $this->MOD_SETTINGS['display'];		// Set wsid to the value from the menu (displaying content of other workspaces)
			$filter = $this->MOD_SETTINGS['filter'];
		} else {
			$wsid = $GLOBALS['BE_USER']->workspace;
			$filter = 0;
		}

			// Initialize workspace object and request all pending versions:
		$wslibObj = t3lib_div::makeInstance('wslib');

			// Selecting ALL versions belonging to the workspace:
		$versions = $wslibObj->selectVersionsInWorkspace($wsid, $filter);

			// Traverse versions and build page-display array:
		$pArray = array();
		foreach($versions as $table => $records)	{
			foreach($records as $rec)	{
				$pageIdField = $table==='pages' ? 't3ver_oid' : 'realpid';
				$this->displayWorkspaceOverview_setInPageArray(
					$pArray,
					t3lib_BEfunc::BEgetRootLine($rec[$pageIdField], 'AND 1=1'),
					$table,
					$rec
				);
			}
		}

			// Make header of overview:
		$tableRows = array();
		$tableRows[] = '
			<tr class="bgColor5 tableheader">
				<td nowrap="nowrap" width="100">Pagetree:</td>
				<td nowrap="nowrap" colspan="2">Online Version:</td>
				<td nowrap="nowrap" colspan="2">Offline Versions:</td>
				<td nowrap="nowrap">Publish:</td>
				<td>Lifecycle:</td>
				'.($this->showWorkspaceCol ? '<td>Workspace:</td>' : '').'
			</tr>';

			// Add lines from overview:
		$tableRows = array_merge($tableRows, $this->displayWorkspaceOverview_list($pArray));

		$table = '<table border="0" cellpadding="0" cellspacing="1" class="lrPadding workspace-overview">'.implode('',$tableRows).'</table>';


		return $table.$this->markupNewOriginals();
	}

	/**
	 * Rendering the content for the publish / review overview:
	 * (Made for internal recursive calling)
	 *
	 * @param	array		Hierarchical storage of the elements to display (see displayWorkspaceOverview() / displayWorkspaceOverview_setInPageArray())
	 * @param	array		Existing array of table rows to add to
	 * @param	array		Depth counter
	 * @return	array		Table rows, see displayWorkspaceOverview()
	 */
	function displayWorkspaceOverview_list($pArray, $tableRows=array(), $c=0, $warnAboutVersions=FALSE)	{
		global $TCA;

			// Initialize:
		$fullColSpan = ($this->showWorkspaceCol?8:7);

			// Traverse $pArray
		if (is_array($pArray))	{
			foreach($pArray as $k => $v)	{
				if (t3lib_div::testInt($k))	{

						// If there are elements on this level, output a divider row which just creates a little visual space.
					if (is_array($pArray[$k.'_']))	{
						$tableRows[] = '
							<tr>
								<td colspan="'.$fullColSpan.'"><img src="clear.gif" width="1" height="3" alt="" /></td>
							</tr>';
					}

						// Printing a level from the page tree with no additional content:
						// If there are NO elements on this level OR if there are NO pages on a level with elements, then show page tree icon and title (otherwise it is shown with the display of the elements)
					if (!is_array($pArray[$k.'_']) || !is_array($pArray[$k.'_']['pages']))	{
						$tableRows[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap" colspan="'.$fullColSpan.'">'.
									$this->displayWorkspaceOverview_pageTreeIconTitle($k,$pArray[$k],$c).
									'</td>
							</tr>';
					}

						// If there ARE elements on this level, print them:
					$warnAboutVersions_next = $warnAboutVersions;
					$warnAboutVersions_nonPages = FALSE;
					$warnAboutVersions_page = FALSE;
					if (is_array($pArray[$k.'_']))	{
						foreach($pArray[$k.'_'] as $table => $oidArray)	{
							foreach($oidArray as $oid => $recs)	{

									// Get CURRENT online record and icon based on "t3ver_oid":
								$rec_on = t3lib_BEfunc::getRecord($table,$oid);
								$icon = t3lib_iconWorks::getIconImage($table, $rec_on, $this->doc->backPath,' align="top" title="'.t3lib_BEfunc::getRecordIconAltText($rec_on,$table).'"');
								if ($GLOBALS['BE_USER']->workspace===0) {	// Only edit online records if in ONLINE workspace:
									$icon = $this->doc->wrapClickMenuOnIcon($icon, $table, $rec_on['uid'], 2, '', '+edit,view,info,delete');
								}

									// MAIN CELL / Online version display:
									// Create the main cells which will span over the number of versions there is. If the table is "pages" then it will show the page tree icon and title (which was not shown by the code above)
								$verLinkUrl = t3lib_extMgm::isLoaded('version') && $TCA[$table]['ctrl']['versioningWS'];
								$origElement = $icon.
									($verLinkUrl ? '<a href="'.htmlspecialchars($this->doc->backPath.t3lib_extMgm::extRelPath('version').'cm1/index.php?table='.$table.'&uid='.$rec_on['uid']).'">' : '').
									t3lib_BEfunc::getRecordTitle($table,$rec_on,TRUE).
									($verLinkUrl ? '</a>' : '');
								$mainCell_rowSpan = count($recs)>1 ? ' rowspan="'.count($recs).'"' : '';
								$mainCell = $table==='pages' ? '
											<td class="bgColor4-20" nowrap="nowrap"'.$mainCell_rowSpan.'>'.
											$this->displayWorkspaceOverview_pageTreeIconTitle($k,$pArray[$k],$c).
											'</td>' : '
											<td class="bgColor"'.$mainCell_rowSpan.'></td>';
								$mainCell.= '
											<td align="center"'.$mainCell_rowSpan.'>'.$this->formatVerId($rec_on['t3ver_id']).'</td>
											<td nowrap="nowrap"'.$mainCell_rowSpan.'>'.
												$origElement.
												'###SUB_ELEMENTS###'.	// For substitution with sub-elements, if any.
											'</td>';

									// Offline versions display:
									// Traverse the versions of the element
								foreach($recs as $rec)	{

										// Get the offline version record and icon:
									$rec_off = t3lib_BEfunc::getRecord($table,$rec['uid']);
									$icon = t3lib_iconWorks::getIconImage($table, $rec_off, $this->doc->backPath, ' align="top" title="'.t3lib_BEfunc::getRecordIconAltText($rec_off,$table).'"');
									$icon = $this->doc->wrapClickMenuOnIcon($icon, $table, $rec_off['uid'], 2, '', '+edit,view,info,delete');

										// Prepare diff-code:
									if ($this->MOD_SETTINGS['diff'])	{
										if ($rec_on['t3ver_state']!=1)	{	// Not new record:
											list($diffHTML,$diffPct) = $this->createDiffView($table, $rec_off, $rec_on);
											$diffCode = ($diffPct<0 ? 'N/A' : ($diffPct ? $diffPct.'% change:' : '')).
														$diffHTML;
										} else {
											$diffCode = $this->doc->icons(1).'New element';
										}
									} else $diffCode = '';

										// Prepare swap-mode values:
									if ($table==='pages' && $rec_off['t3ver_swapmode']!=-1)	{
										if ($rec_off['t3ver_swapmode']>0)	{
											$swapLabel = ' [Branch]';
											$swapClass = 'ver-branch';
											$warnAboutVersions_next = !$this->showWorkspaceCol;		// This value is true only if multiple workspaces are shown and we need the opposite here.
										} else {
											$swapLabel = ' [Page]';
											$swapClass = 'ver-page';
											$warnAboutVersions_page = !$this->showWorkspaceCol;		// This value is true only if multiple workspaces are shown and we need the opposite here.
										}
									} else {
										$swapLabel = ' [Element]';
										$swapClass = 'ver-element';
										$warnAboutVersions_nonPages = $warnAboutVersions_page;	// Setting this if sub elements are found with a page+content (must be rendered prior to this of course!)
									}

										// Modify main cell based on first version shown:
									$subElements = array();
									if ($table==='pages' && $rec_off['t3ver_swapmode']!=-1 && $mainCell)	{	// For "Page" and "Branch" swap modes where $mainCell is still carrying content (only first version)
										$subElements['on'] = $this->subElements($rec_on['uid'], $rec_off['t3ver_swapmode']);
										$subElements['off'] = $this->subElements($rec_off['uid'],$rec_off['t3ver_swapmode'],$rec_on['uid']);
									}
									$mainCell = str_replace('###SUB_ELEMENTS###', $subElements['on'], $mainCell);

										// Create version element:
									$multipleWarning = (!$mainCell && $GLOBALS['BE_USER']->workspace!==0? '<br/>'.$this->doc->icons(3).'<b>Multiple versions in same workspace!</b>' : '');
									$verWarning = $warnAboutVersions || $warnAboutVersions_nonPages ? '<br/>'.$this->doc->icons(3).'<b>Version inside version!</b>' : '';
									$verElement = $icon.
										'<a href="'.htmlspecialchars('index.php?details='.rawurlencode($table.':'.$rec_off['uid'])).'">'.
										t3lib_BEfunc::getRecordTitle($table,$rec_off,TRUE).
										'</a>'.
										$multipleWarning.
										$verWarning;
									if ($diffCode)	{
										$verElement = '
										<table border="0" cellpadding="0" cellspacing="0" class="ver-verElement">
											<tr>
												<td nowrap="nowrap" width="180">'.$verElement.'&nbsp;&nbsp;</td>
												<td class="c-diffCell">'.$diffCode.'</td>
											</tr>
										</table>';
									}

										// Create version cell:
									$verCell = '
											<td align="center">'.$this->formatVerId($rec_off['t3ver_id']).'</td>
											<td nowrap="nowrap">'.
												$verElement.
												$subElements['off'].
												'</td>';

										// Compile table row:
									$tableRows[] = '
										<tr class="bgColor4">
											'.$mainCell.$verCell.'
											<td nowrap="nowrap" class="'.$swapClass.'">'.
												$this->displayWorkspaceOverview_commandLinks($table,$rec_on,$rec_off).
												htmlspecialchars($swapLabel).
												'</td>
											<td nowrap="nowrap">'.htmlspecialchars($this->formatCount($rec_off['t3ver_count'])).'</td>'.		// Lifecycle
												($this->showWorkspaceCol ? '
											<td nowrap="nowrap">'.htmlspecialchars($this->formatWorkspace($rec_off['t3ver_wsid'])).'</td>' : '').'
										</tr>';

										// Reset the main cell:
									$mainCell = '';
								}
							}
						}
					}
						// Call recursively for sub-rows:
					$tableRows = $this->displayWorkspaceOverview_list($pArray[$k.'.'], $tableRows, $c+1, $warnAboutVersions_next);
				}
			}
		}
		return $tableRows;
	}

	/**
	 * Create indentation, icon and title for the page tree identification for the list.
	 *
	 * @param	integer		Page UID (record will be looked up again)
	 * @param	string		Page title
	 * @param	integer		Depth counter from displayWorkspaceOverview_list() used to indent the icon and title
	 * @return	string		HTML content
	 */
	function displayWorkspaceOverview_pageTreeIconTitle($pageUid, $title, $indentCount)	{
		$pRec = t3lib_BEfunc::getRecord('pages',$pageUid);
		return '<img src="clear.gif" width="1" height="1" hspace="'.($indentCount * $this->pageTreeIndent).'" align="top" alt="" />'.	// Indenting page tree
					t3lib_iconWorks::getIconImage('pages',$pRec,$this->doc->backPath,' align="top" title="'.t3lib_BEfunc::getRecordIconAltText($pRec,'pages').'"').
					htmlspecialchars(t3lib_div::fixed_lgd_cs($title,$this->pageTreeIndent_titleLgd)).
					'&nbsp;&nbsp;';
	}

	/**
	 * Links to publishing etc of a version
	 *
	 * @param	string		Table name
	 * @param	array		Online record
	 * @param	array		Offline record (version)
	 * @param	string		Swap-mode for the CMD array (based on t3ver_swapmode for pages, otherwise it should be empty)
	 * @return	string		HTML content, mainly link tags and images.
	 */
	function displayWorkspaceOverview_commandLinks($table,&$rec_on,&$rec_off)	{
		$actionLinks =
			'<a href="'.htmlspecialchars($this->doc->issueCommand(
					'&cmd['.$table.']['.$rec_on['uid'].'][version][action]=swap'.
					'&cmd['.$table.']['.$rec_on['uid'].'][version][swapWith]='.$rec_off['uid']
					)).'">'.
			'<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/insert1.gif','width="14" height="14"').' alt="" align="top" title="Publish" />'.
			'</a>';
		$actionLinks.=
			'<a href="'.htmlspecialchars($this->doc->issueCommand(
					'&cmd['.$table.']['.$rec_on['uid'].'][version][action]=swap'.
					'&cmd['.$table.']['.$rec_on['uid'].'][version][swapWith]='.$rec_off['uid'].
					'&cmd['.$table.']['.$rec_on['uid'].'][version][swapIntoWS]=1'
					)).'">'.
			'<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/swap.png','width="14" height="14"').' alt="" align="top" title="Swap" />'.
			'</a>';

			// Release
		$actionLinks.=
			'<a href="'.htmlspecialchars($this->doc->issueCommand('&cmd['.$table.']['.$rec_off['uid'].'][version][action]=clearWSID')).'">'.
			'<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/group_clear.gif','width="14" height="14"').' alt="" align="top" title="Remove from workspace" />'.
			'</a>';

		return $actionLinks;
	}

	/**
	 * Building up of the $pArray variable which is a hierarchical storage of table-rows arranged according to the level in the rootline the element was found
	 * (Internal)
	 * Made for recursive calling
	 *
	 * @param	array		Array that is built up with the page tree structure
	 * @param	array		Root line for element (table / row); The element is stored in pArray according to this root line.
	 * @param	string		Table name
	 * @param	array		Table row
	 * @return	void		$pArray is passed by reference and modified internally
	 */
	function displayWorkspaceOverview_setInPageArray(&$pArray,$rlArr,$table,$row)	{

			// Initialize:
		ksort($rlArr);
		reset($rlArr);
		if (!$rlArr[0]['uid'])		array_shift($rlArr);

			// Get and remove first element in root line:
		$cEl = current($rlArr);
		$pUid = $cEl['t3ver_oid'] ? $cEl['t3ver_oid'] : $cEl['uid'];		// Done to pile up "false versions" in the right branch...

		$pArray[$pUid] = $cEl['title'];
		array_shift($rlArr);

			// If there are elements left in the root line, call this function recursively (to build $pArray in depth)
		if (count($rlArr))	{
			if (!isset($pArray[$pUid.'.']))	$pArray[$pUid.'.'] = array();
			$this->displayWorkspaceOverview_setInPageArray($pArray[$pUid.'.'],$rlArr,$table,$row);
		} else {	// If this was the last element, set the value:
			$pArray[$pUid.'_'][$table][$row['t3ver_oid']][] = $row;
		}
	}

	/**
	 * Creates display of sub elements of a page when the swap mode is either "Page" or "Branch" (0 / ALL)
	 *
	 * @param	integer		Page uid (for either online or offline version, but it MUST have swapmode/treeLevel set to >0 (not -1 indicating element versioning)
	 * @param	integer		The treeLevel value, >0 indicates "branch" while 0 means page+content. (-1 would have meant element versioning, but that should never happen for a call to this function!)
	 * @param	integer		For offline versions; This is t3ver_oid, the original ID of the online page.
	 * @return	string		HTML content.
	 */
	function subElements($uid,$treeLevel,$origId=0)	{
		global $TCA;

		if ($GLOBALS['BE_USER']->workspace===0 || !$this->MOD_SETTINGS['expandSubElements'])	{	// In online workspace we have a reduced view because otherwise it will bloat the listing:
			return '<br/>
					<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/ol/joinbottom.gif','width="18" height="16"').' align="top" alt="" title="" />'.
					($origId ?
						'<a href="index.php?details='.$uid.'"><span class="typo3-dimmed"><em>[Sub elements, click for details]</em><span></a>' :
						'<span class="typo3-dimmed"><em>[Sub elements]</em><span>');
		} else {	// For an offline workspace, show sub elements:

			$tCell = array();

				// Find records that follow pages when swapping versions:
			$recList = array();
			foreach($TCA as $tN => $tCfg)	{
				if ($tN!='pages' && ($treeLevel>0 || $TCA[$tN]['ctrl']['versioning_followPages']))	{
					$this->subElements_getNonPageRecords($tN, $uid, $recList);
				}
			}

				// Render records collected above:
			$elCount = count($recList)-1;
			foreach($recList as $c => $comb)	{
				list($tN,$rec) = $comb;

				$this->subElements_renderItem(
					$tCell,
					$tN,
					$uid,
					$rec,
					$origId,
					$c==$elCount && $treeLevel==0 ? 1 : 0,		// If true, will show bottom-join icon.
					''
				);
			}

				// For branch, dive into the subtree:
			if ($treeLevel>0) {

					// Drawing tree:
				$tree = t3lib_div::makeInstance('t3lib_pageTree');
				$tree->init('AND '.$GLOBALS['BE_USER']->getPagePermsClause(1));
				$tree->makeHTML = 2;		// 2=Also rendering depth-data into the result array
				$tree->getTree($uid, 99, '');

					// Traverse page tree:
				foreach($tree->tree as $data)	{

						// Render page in table cell:
					$this->subElements_renderItem(
						$tCell,
						'pages',
						$uid,
						t3lib_BEfunc::getRecord('pages',$data['row']['uid']),	// Needs all fields, at least more than what is given in $data['row']...
						$origId,
						2,		// 2=the join icon and icon for the record is not rendered for pages (where all is in $data['HTML']
						$data['HTML']
					);

						// Find all records from page and collect in $recList:
					$recList = array();
					foreach($TCA as $tN => $tCfg)	{
						if ($tN!=='pages')	{
							$this->subElements_getNonPageRecords($tN, $data['row']['uid'], $recList);
						}
					}

						// Render records collected above:
					$elCount = count($recList)-1;
					foreach($recList as $c => $comb)	{
						list($tN,$rec) = $comb;

						$this->subElements_renderItem(
							$tCell,
							$tN,
							$uid,
							$rec,
							$origId,
							$c==$elCount?1:0,	// If true, will show bottom-join icon.
							$data['HTML_depthData']
						);
					}
				}
			}

			return '
					<!-- Sub-element tree for versions -->
					<table border="0" cellpadding="0" cellspacing="1" class="ver-subtree">
						'.implode('',$tCell).'
					</table>';
		}
	}

	/**
	 * Select records from a table and add them to recList
	 *
	 * @param	string		Table name (from TCA)
	 * @param	integer		PID to select records from
	 * @param	array		Array where records are accumulated, passed by reference
	 * @return	void
	 */
	function subElements_getNonPageRecords($tN, $uid, &$recList)	{
		global $TCA;

		$records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			$tN,
			'pid='.intval($uid).
				($TCA[$tN]['ctrl']['versioningWS'] ? ' AND t3ver_state=0' : '').
				t3lib_BEfunc::deleteClause($tN),
			'',
			$TCA[$tN]['ctrl']['sortby'] ? $TCA[$tN]['ctrl']['sortby'] : $GLOBALS['TYPO3_DB']->stripOrderBy($TCA[$tN]['ctrl']['default_sortby'])
		);

		foreach($records as $rec)	{
			$recList[] = array($tN,$rec);
		}
	}

	/**
	 * Render a single item in a subelement list into a table row:
	 *
	 * @param	array		Table rows, passed by reference
	 * @param	string		Table name
	 * @param	integer		Page uid for which the subelements are selected/shown
	 * @param	array		Row of element in list
	 * @param	integer		The uid of the online version of $uid. If zero it means we are drawing a row for the online version itself while a value means we are drawing display for an offline version.
	 * @param	integer		Mode of icon display: 0=not the last, 1= is the last in list (make joinbottom icon then), 2=do not shown icons are all (for pages from the page tree already rendered)
	 * @param	string		Prefix HTML data (icons for tree rendering)
	 * @return	void		(Content accumulated in $tCell!)
	 */
	function subElements_renderItem(&$tCell,$tN,$uid,$rec,$origId,$iconMode,$HTMLdata)	{
		global $TCA;

			// Initialize:
		$origUidFields = $TCA[$tN]['ctrl']['origUid'];
		$diffCode = '';

		if ($origUidFields)	{	// If there is a field for this table with original uids we will use that to connect records:
			if (!$origId)	{	// In case we are displaying the online originals:
				$this->targets['orig_'.$uid.'_'.$tN.'_'.$rec['uid']] = $rec;	// Build up target array (important that
				$tdParams =  ' id="orig_'.$uid.'_'.$tN.'_'.$rec['uid'].'" class="typo3-ver"';		// Setting ID of the table row
			} else {	// Version branch:
				if ($this->targets['orig_'.$origId.'_'.$tN.'_'.$rec[$origUidFields]])	{	// If there IS a corresponding original record...:

						// Prepare Table row parameters:
					$tdParams =  ' onmouseover="hlSubelements(\''.$origId.'_'.$tN.'_'.$rec[$origUidFields].'\', \''.$uid.'_'.$tN.'_'.$rec[$origUidFields].'\', 1, '.($this->MOD_SETTINGS['diff']==2?1:0).');"'.
								' onmouseout="hlSubelements(\''.$origId.'_'.$tN.'_'.$rec[$origUidFields].'\', \''.$uid.'_'.$tN.'_'.$rec[$origUidFields].'\', 0, '.($this->MOD_SETTINGS['diff']==2?1:0).');"'.
								' id="ver_'.$uid.'_'.$tN.'_'.$rec[$origUidFields].'" class="typo3-ver"';

						// Create diff view:
					if ($this->MOD_SETTINGS['diff'])	{
						list($diffHTML,$diffPct) = $this->createDiffView($tN, $rec, $this->targets['orig_'.$origId.'_'.$tN.'_'.$rec[$origUidFields]]);

						if ($this->MOD_SETTINGS['diff']==2)	{
							$diffCode =
								($diffPct ? '<span class="nobr">'.$diffPct.'% change</span>' : '-').
								'<div style="visibility: hidden; position: absolute;" id="diff_'.$uid.'_'.$tN.'_'.$rec[$origUidFields].'" class="diffLayer">'.
								$diffHTML.
								'</div>';
						} else {
							$diffCode =
								($diffPct<0 ? 'N/A' : ($diffPct ? $diffPct.'% change:' : '')).
								$diffHTML;
						}
					}

						// Unsetting the target fields allows us to mark all originals without a version in the subtree (see ->markupNewOriginals())
					unset($this->targets['orig_'.$origId.'_'.$tN.'_'.$rec[$origUidFields]]);
				} else {	// No original record, so must be new:
					$tdParams =  ' class="typo3-ver-new"';
				}
			}
		} else {	// If no original uid column is supported for this table we are forced NOT to display any diff or highlighting.
			$tdParams = ' class="typo3-ver-noComp"';
		}

			// Compile the cell:
		$tCell[] = '
						<tr'.$tdParams.'>
							<td class="iconTitle">'.
								$HTMLdata.
								($iconMode < 2 ?
									'<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/ol/join'.($iconMode ? 'bottom' : '').'.gif','width="18" height="16"').' alt="" />'.
									t3lib_iconWorks::getIconImage($tN, $rec, $this->doc->backPath,'') : '').
								t3lib_BEfunc::getRecordTitle($tN, $rec, TRUE).
							'</td>'.($origId ? '<td class="diffCell">'.
								$diffCode.
							'</td>':'').'
						</tr>';
	}

	/**
	 * JavaScript code to mark up new records that are online (in sub element lists)
	 *
	 * @return	string		HTML javascript section
	 */
	function markupNewOriginals()	{

		if (count($this->targets))	{
			$scriptCode = '';
			foreach($this->targets as $key => $rec)	{
				$scriptCode.='
					document.getElementById(\''.$key.'\').attributes.getNamedItem("class").nodeValue = \'typo3-ver-new\';
				';
			}

			return $this->doc->wrapScriptTags($scriptCode);
		}
	}

	/**
	 * Create visual difference view of two records. Using t3lib_diff library
	 *
	 * @param	string		Table name
	 * @param	array		New version record (green)
	 * @param	array		Old version record (red)
	 * @return	array		Array with two keys (0/1) with HTML content / percentage integer (if -1, then it means N/A) indicating amount of change
	 */
	function createDiffView($table, $diff_1_record, $diff_2_record)	{
		global $TCA;

			// Initialize:
		$pctChange = 'N/A';

			// Check that records are arrays:
		if (is_array($diff_1_record) && is_array($diff_2_record))	{

				// Load full table description and initialize diff-object:
			t3lib_div::loadTCA($table);
			$t3lib_diff_Obj = t3lib_div::makeInstance('t3lib_diff');

				// Add header row:
			$tRows = array();
			$tRows[] = '
				<tr class="bgColor5 tableheader">
					<td>Fieldname:</td>
					<td width="98%" nowrap="nowrap">Colored diff-view:</td>
				</tr>
			';

				// Initialize variables to pick up string lengths in:
			$allStrLen = 0;
			$diffStrLen = 0;

				// Traversing the first record and process all fields which are editable:
			foreach($diff_1_record as $fN => $fV)	{
				if ($TCA[$table]['columns'][$fN] && $TCA[$table]['columns'][$fN]['config']['type']!='passthrough' && !t3lib_div::inList('t3ver_label',$fN))	{

						// Check if it is files:
					$isFiles = FALSE;
					if (strcmp($diff_1_record[$fN],$diff_2_record[$fN]) &&
							$TCA[$table]['columns'][$fN]['config']['type']=='group' &&
							$TCA[$table]['columns'][$fN]['config']['internal_type']=='file')	{

							// Initialize:
						$uploadFolder = $TCA[$table]['columns'][$fN]['config']['uploadfolder'];
						$files1 = array_flip(t3lib_div::trimExplode(',', $diff_1_record[$fN],1));
						$files2 = array_flip(t3lib_div::trimExplode(',', $diff_2_record[$fN],1));

							// Traverse filenames and read their md5 sum:
						foreach($files1 as $filename => $tmp)	{
							$files1[$filename] = @is_file(PATH_site.$uploadFolder.'/'.$filename) ? md5(t3lib_div::getUrl(PATH_site.$uploadFolder.'/'.$filename)) : $filename;
						}
						foreach($files2 as $filename => $tmp)	{
							$files2[$filename] = @is_file(PATH_site.$uploadFolder.'/'.$filename) ? md5(t3lib_div::getUrl(PATH_site.$uploadFolder.'/'.$filename)) : $filename;
						}

							// Implode MD5 sums and set flag:
						$diff_1_record[$fN] = implode(' ',$files1);
						$diff_2_record[$fN] = implode(' ',$files2);
						$isFiles = TRUE;
					}

						// If there is a change of value:
					if (strcmp($diff_1_record[$fN],$diff_2_record[$fN]))	{


							// Get the best visual presentation of the value and present that:
						$val1 = t3lib_BEfunc::getProcessedValue($table,$fN,$diff_2_record[$fN],0,1);
						$val2 = t3lib_BEfunc::getProcessedValue($table,$fN,$diff_1_record[$fN],0,1);

							// Make diff result and record string lenghts:
						$diffres = $t3lib_diff_Obj->makeDiffDisplay($val1,$val2,$isFiles?'div':'span');
						$diffStrLen+= $t3lib_diff_Obj->differenceLgd;
						$allStrLen+= strlen($val1.$val2);

							// If the compared values were files, substituted MD5 hashes:
						if ($isFiles)	{
							$allFiles = array_merge($files1,$files2);
							foreach($allFiles as $filename => $token)	{
								if (strlen($token)==32 && strstr($diffres,$token))	{
									$filename =
										t3lib_BEfunc::thumbCode(array($fN=>$filename),$table,$fN,$this->doc->backPath).
										$filename;
									$diffres = str_replace($token,$filename,$diffres);
								}
							}
						}

							// Add table row with result:
						$tRows[] = '
							<tr class="bgColor4">
								<td>'.htmlspecialchars($GLOBALS['LANG']->sL(t3lib_BEfunc::getItemLabel($table,$fN))).'</td>
								<td width="98%">'.$diffres.'</td>
							</tr>
						';
					} else {
							// Add string lengths even if value matched - in this was the change percentage is not high if only a single field is changed:
						$allStrLen+=strlen($diff_1_record[$fN].$diff_2_record[$fN]);
					}
				}
			}

				// Calculate final change percentage:
			$pctChange = $allStrLen ? ceil($diffStrLen*100/$allStrLen) : -1;

				// Create visual representation of result:
			if (count($tRows)>1)	{
				$content.= '<table border="0" cellpadding="1" cellspacing="1" class="diffTable">'.implode('',$tRows).'</table>';
			} else {
				$content.= '<span class="nobr">'.$this->doc->icons(1).'Complete match on editable fields.</span>';
			}
		} else $content.= $this->doc->icons(3).'ERROR: Records could strangely not be found!';

			// Return value:
		return array($content,$pctChange);
	}












	/********************************
	 *
	 * Module content: Workspace list
	 *
	 ********************************/

	/**
	 * Rendering of the workspace list
	 *
	 * @return	string		HTML
	 */
	function moduleContent_workspaceList()	{
		return nl2br('
			TODO: Workspace listing

			- LISTING: Shows list of available workspaces for user. Used can see title, description, publication time, freeze-state, db-mount, member users/groups etc. Current workspace is indicated.
			- SWITCHING: Switching between available workspaces is done by a button shown for each in the list
			- ADMIN: Administrator of a workspace can click an edit-button linking to a form where he can edit the workspace. Users and groups should be selected based on some filtering so he cannot select groups he is not a member off himself (or some other rule... like for permission display with blinded users/groups)
			- CREATE: If allowed, the user can create a new workspace which brings up a form where he can enter basic data. This is saved by a local instance of tcemain with forced admin-rights (creation in pid=0!).
		');
	}


























	/**************************************
	 *
	 * Helper functions
	 *
	 *************************************/

	/**
	 * Formatting the version number for HTML output
	 *
	 * @param	integer		Version number
	 * @return	string		Version number for output
	 */
	function formatVerId($verId)	{
		return '1.'.$verId;
	}

	/**
	 * Formatting workspace ID into a visual label
	 *
	 * @param	integer		Workspace ID
	 * @return	string		Workspace title
	 */
	function formatWorkspace($wsid)	{

			// Render, if not cached:
		if (!isset($this->formatWorkspace_cache[$wsid]))	{
			switch($wsid)	{
				case -1:
					$this->formatWorkspace_cache[$wsid] = '[Offline]';
				break;
				case 0:
					$this->formatWorkspace_cache[$wsid] = '';	// Does not output anything for ONLINE because it might confuse people to think that the elemnet IS online which is not the case - only that it exists as an offline version in the online workspace...
				break;
				default:
					list($titleRec) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('title','sys_workspace','uid='.intval($wsid).t3lib_BEfunc::deleteClause('sys_workspace'));
					$this->formatWorkspace_cache[$wsid] = '['.$wsid.'] '.$titleRec['title'];
				break;
			}
		}

		return $this->formatWorkspace_cache[$wsid];
	}

	/**
	 * Format publishing count for version (lifecycle state)
	 *
	 * @param	integer		t3ver_count value (number of times it has been online)
	 * @return	string		String translation of count.
	 */
	function formatCount($count)	{

			// Render, if not cached:
		if (!isset($this->formatCount_cache[$count]))	{
			switch($count)	{
				case 0:
					$this->formatCount_cache[$count] = 'Draft';
				break;
				case 1:
					$this->formatCount_cache[$count] = 'Archive';
				break;
				default:
					$this->formatCount_cache[$count] = 'Published '.$count.' times';
				break;
			}
		}

		return $this->formatCount_cache[$count];
	}











	/**********************************
	 *
	 * Processing
	 *
	 **********************************/

	/**
	 * Will publish workspace if buttons are pressed
	 *
	 * @return	void
	 */
	function publishAction()	{

			// If "Publish" or "Swap" buttons are pressed:
		if (t3lib_div::_POST('_publish') || t3lib_div::_POST('_swap'))	{

				// Initialize workspace object and request all pending versions:
			$wslibObj = t3lib_div::makeInstance('wslib');
			$cmd = $wslibObj->getCmdArrayForPublishWS($GLOBALS['BE_USER']->workspace, t3lib_div::_POST('_swap'));

				// Execute the commands:
			$tce = t3lib_div::makeInstance('t3lib_TCEmain');
			$tce->stripslashes_values = 0;
			$tce->start(array(), $cmd);
			$tce->process_cmdmap();
		}
	}
}

// Include extension?
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['typo3/mod/user/ws/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['typo3/mod/user/ws/index.php']);
}









// Make instance:
$SOBE = t3lib_div::makeInstance('SC_mod_user_ws_index');
$SOBE->init();
$SOBE->main();
$SOBE->printContent();
?>