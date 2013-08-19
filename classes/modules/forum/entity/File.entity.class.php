<?php
/*---------------------------------------------------------------------------
* @Module Name: Forum
* @Description: Forum for LiveStreet
* @Version: 1.0
* @Author: Chiffa
* @LiveStreet version: 1.X
* @File Name: File.entity.class.php
* @License: CC BY-NC, http://creativecommons.org/licenses/by-nc/3.0/
*----------------------------------------------------------------------------
*/

class PluginForum_ModuleForum_EntityFile extends EntityORM {
	protected $aRelations = array(
		'post'=>array(self::RELATION_TYPE_BELONGS_TO,'ModuleUser_EntityUser','post_id')
	);

	public function getSizeFormat() {
		$aSizes = array(' Bytes',' KB',' MB',' GB',' TB',' PB',' EB',' ZB',' YB');
		$iSize = $this->getSize();
		return $iSize
			? round($iSize/pow(1024,($i = floor(log($iSize, 1024)))), 2).$aSizes[$i]
			: '0 '.$aSizes[0];
	}

}

?>