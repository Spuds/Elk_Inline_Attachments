<?php

/**
 * @name      Inline Attachments (ILA)
 * @license   Mozilla Public License version 1.1 http://www.mozilla.org/MPL/1.1/.
 * @author    Spuds
 * @copyright (c) 2014 Spuds
 *
 * @version   1.0
 *
 * Based on original code by mouser http://www.donationcoder.com
 * Updated/Modified/etc with permission
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ila_bbc_add_code()
 *
 * - Adds in new BBC code tags for use with inline images
 *
 * @param mixed[] $codes
 */
function ila_bbc_add_code(&$codes)
{
	// Add in our new codes, if found on to the end of this array
	// here mostly used to null them out should they not be rendered
	$codes = array_merge($codes, array(
		array(
			'tag' => 'attachimg',
			'type' => 'closed',
			'content' => '',
		),
		array(
			'tag' => 'attachurl',
			'type' => 'closed',
			'content' => '',
		),
		array(
			'tag' => 'attachmini',
			'type' => 'closed',
			'content' => '',
		),
		array(
			'tag' => 'attachthumb',
			'type' => 'closed',
			'content' => '',
		))
	);

	return;
}

/**
 * Admin Hook, integrate_admin_areas, called from Admin.php
 * used to add/modify admin menu areas
 *
 * - add a line under addon config
 *
 * @param mixed $admin_areas
 */
function ila_integrate_admin_areas(&$admin_areas)
{
	global $txt;

	$admin_areas['config']['areas']['addonsettings']['subsections']['ila'] = array($txt['mods_cat_modifications_ila']);
}

/**
 * ila_integrate_sa_modify_modifications()
 *
 * - Admin Hook, integrate_sa_modify_modifications, called from AddonSettings.controller.php
 * - Used to add subactions to the addon area
 *
 * @param mixed $sub_actions
 */
function ila_integrate_sa_modify_modifications(&$sub_actions)
{
	$sub_actions['ila'] = array(
		'dir' => SOURCEDIR,
		'file' => 'ILA.integration.php',
		'function' => 'ila_settings',
		'permission' => 'admin_forum',
	);
}

/**
 * ModifyilaSettings()
 *
 * - Defines our settings array and uses our settings class to manage the data
 */
function ila_settings()
{
	global $txt, $scripturl, $context;

	$context[$context['admin_menu_name']]['tab_data']['tabs']['ila']['description'] = $txt['ila_desc'];

	// Lets build a settings form
	require_once(SUBSDIR . '/Settings.class.php');

	// Instantiate the form
	$ilaSettings = new Settings_Form();

	$config_vars = array(
		array('check', 'ila_enabled'),
		array('check', 'ila_alwaysfullsize'),
		array('check', 'ila_basicmenu'),
	);

	// Load the settings to the form class
	$ilaSettings->settings($config_vars);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Enabling the addon then lets have the main file available, otherwise lets not ;)
		if (isset($_POST['ila_enabled']))
			add_integration_function('integrate_pre_include', 'SOURCEDIR/ILA.subs.php');
		else
			remove_integration_function('integrate_pre_include', 'SOURCEDIR/ILA.subs.php');

		Settings_Form::save_db($config_vars);
		redirectexit('action=admin;area=addonsettings;sa=ila');
	}

	// Continue on to the settings template
	$context['page_title'] = $txt['mods_cat_modifications_ila'];
	$context['post_url'] = $scripturl . '?action=admin;area=addonsettings;save;sa=ila';
	$context['settings_title'] = $txt['mods_cat_modifications_ila'];

	Settings_Form::prepare_db($config_vars);
}

/**
 * Subs hook, integrate_pre_parsebbc
 *
 * - Allow addons access before entering the main parse_bbc loop
 * - Prevents parseBBC from working on these tags at all
 *
 * @param string $message
 * @param smixed[] $smileys
 * @param string $cache_id
 * @param string[]|null $parse_tags
 */
function ila_integrate_pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
{
	global $context, $modSettings;

	// Enabled and we have ila tags, then hide them from parsebbc where approriate
	if (!empty($modSettings['ila_enabled']) && empty($parse_tags) && empty($context['uninstalling']) && stripos($message, '[attach') !== false)
	{
		require_once(SUBSDIR . '/ILA.subs.php');
		ila_hide_bbc($message);
	}
}

/**
 * Subs hook, integrate_post_parsebbc
 *
 * - Allow addons access to what parse_bbc created, here we call ILA to render its tags
 *
 * @param string $message
 * @param smixed[] $smileys
 * @param string $cache_id
 * @param string[]|null $parse_tags
 */
function ila_integrate_post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
{
	global $context, $modSettings;

	// Enabled and we have tags, time to render them
	if (!empty($modSettings['ila_enabled']) && empty($parse_tags) && empty($context['uninstalling']) && stripos($message, '[attach') !== false)
	{
		$ila_parser = new ILA_Parse_BBC($message, $cache_id);
		$message = $ila_parser->ila_parse_bbc();
	}
}