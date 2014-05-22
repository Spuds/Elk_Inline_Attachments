<?php

/**
 * @name      Inline Attachments (ILA)
 * @copyright Spuds
 * @license   MPL 1.1 http://mozilla.org/MPL/1.1/
 *
 * @version 1.0
 *
 */

// If we have found SSI.php and we are outside of ELK, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as ELK\'s SSI.php.');

if ((ELK == 'SSI') && !$user_info['is_admin'])
	die('Admin priveleges required.');

global $modSettings;

// List settings here in the format: setting_key => default_value.  Escape any "s. (" => \")
$mod_settings = array(
	'ila_alwaysfullsize' => 0,
	'ila_basicmenu' => 0,
);

// Always start off as not enabled .... even on a reinstall
updateSettings(array('ila_enabled' => 0));

// Update mod settings if applicable
foreach ($mod_settings as $new_setting => $new_value)
{
	if (!isset($modSettings[$new_setting]))
		updateSettings(array($new_setting => $new_value));
}

if (ELK == 'SSI')
   echo 'Congratulations! You have successfully installed this addon.';