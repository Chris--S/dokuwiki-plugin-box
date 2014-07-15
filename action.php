<?php
/**
 * Box Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_box extends DokuWiki_Action_Plugin {

	function register(Doku_Event_Handler $controller) {
		$controller->register_hook('ACTION_SHOW_REDIRECT', 'BEFORE', $this, 'act_box_redirect_execute');
	}

	function act_box_redirect_execute( &$event ) {
		global $PRE;
		global $TEXT;

		if ( !empty($event->data['fragment']) ) { return; }
		if ( $event->data['preact'] == 'save' ) { return; }
		
		if($PRE && preg_match('/^\s*<box.*?\|([^>\n]+)/',$TEXT,$match)){
			$check = false; //Byref
			$event->data['fragment'] = sectionID($match[1], $check);
		}
	}
}