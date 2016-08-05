<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2016, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.io/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.io	    http://webgroup.media
***********************************************************************/

class ChDebugController extends DevblocksControllerExtension  {
	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		$stack = $request->path;
		array_shift($stack); // update

//		$cache = DevblocksPlatform::getCacheService(); /* @var $cache _DevblocksCacheManager */
		$settings = DevblocksPlatform::getPluginSettingsService();

		$authorized_ips_str = $settings->get('cerberusweb.core',CerberusSettings::AUTHORIZED_IPS,CerberusSettingsDefaults::AUTHORIZED_IPS);
		$authorized_ips = DevblocksPlatform::parseCrlfString($authorized_ips_str);
		
		$authorized_ip_defaults = DevblocksPlatform::parseCsvString(AUTHORIZED_IPS_DEFAULTS);
		$authorized_ips = array_merge($authorized_ips, $authorized_ip_defaults);
		
		// Is this IP authorized?
		$pass = false;
		foreach ($authorized_ips as $ip) {
			if(substr($ip,0,strlen($ip)) == substr(DevblocksPlatform::getClientIp(),0,strlen($ip))) {
				$pass = true;
				break;
			}
		}
		
		if(!$pass) {
			echo sprintf('Your IP address (%s) is not authorized to debug this helpdesk.  Your administrator needs to authorize your IP in Helpdesk Setup or in the framework.config.php file under AUTHORIZED_IPS_DEFAULTS.',
				DevblocksPlatform::strEscapeHtml(DevblocksPlatform::getClientIp())
			);
			return;
		}
		
		switch(array_shift($stack)) {
			case 'phpinfo':
				phpinfo();
				break;
				
			case 'check':
				echo sprintf(
					"<html>
					<head>
						<title></title>
						<style>
							BODY {font-family: Arial, Helvetica, sans-serif; font-size: 12px;}
							FORM {margin:0px; }
							H1 { margin:0px; }
							.fail {color:red;font-weight:bold;}
							.pass {color:green;font-weight:bold;}
						</style>
					</head>
					<body>
						<h1>Cerb - Requirements Checker:</h1>
					"
				);

				$errors = CerberusApplication::checkRequirements();

				if(!empty($errors)) {
					echo "<ul class='fail'>";
					foreach($errors as $error) {
						echo sprintf("<li>%s</li>",$error);
					}
					echo "</ul>";
					
				} else {
					echo '<span class="pass">Your server is compatible with Cerb '.APP_VERSION.'!</span>';
				}
				
				echo sprintf("
					</body>
					</html>
				");
				
				break;
				
			case 'status':
				@$db = DevblocksPlatform::getDatabaseService();

				header('Content-Type: application/json; charset=' . LANG_CHARSET_CODE);

				$tickets_by_status = array();
				
				foreach($db->GetArrayMaster('SELECT count(*) as hits, status_id from ticket group by status_id') as $row) {
					switch($row['status_id']) {
						case 0:
							$tickets_by_status['open'] = intval($row['hits']);
							break;
						case 1:
							$tickets_by_status['waiting'] = intval($row['hits']);
							break;
						case 2:
							$tickets_by_status['closed'] = intval($row['hits']);
							break;
						case 3:
							$tickets_by_status['deleted'] = intval($row['hits']);
							break;
					}
				}
				
				$status = array(
					'counts' => array(
						'attachments' => intval($db->GetOneMaster('SELECT count(id) FROM attachment')),
						'buckets' => intval($db->GetOneMaster('SELECT count(id) FROM bucket')),
						'comments' => intval($db->GetOneMaster('SELECT count(id) FROM comment')),
						'custom_fields' => intval($db->GetOneMaster('SELECT count(id) FROM custom_field')),
						'custom_fieldsets' => intval($db->GetOneMaster('SELECT count(id) FROM custom_fieldset')),
						'groups' => intval($db->GetOneMaster('SELECT count(id) FROM worker_group')),
						'mailboxes' => intval($db->GetOneMaster('SELECT count(id) FROM mailbox WHERE enabled=1')),
						'mail_transports' => intval($db->GetOneMaster('SELECT count(id) FROM mail_transport')),
						'messages' => intval($db->GetOneMaster('SELECT count(id) FROM message')),
						'messages_stats' => array(
							'received' => intval($db->GetOneMaster('SELECT count(id) FROM message WHERE is_outgoing=0')),
							'received_24h' => intval($db->GetOneMaster(sprintf('SELECT count(id) FROM message WHERE is_outgoing=0 AND created_date >= %d', time()-86400))),
							'sent' => intval($db->GetOneMaster('SELECT count(id) FROM message WHERE is_outgoing=1')),
							'sent_24h' => intval($db->GetOneMaster(sprintf('SELECT count(id) FROM message WHERE is_outgoing=1 AND created_date >= %d', time()-86400))),
						),
						'portals' => intval(@$db->GetOneMaster('SELECT count(id) FROM community_tool')),
						'tickets' => intval($db->GetOneMaster('SELECT count(id) FROM ticket')),
						'tickets_status' => $tickets_by_status,
						'va' => intval($db->GetOneMaster('SELECT count(id) FROM virtual_attendant')),
						'va_behaviors' => intval($db->GetOneMaster('SELECT count(id) FROM trigger_event')),
						'webhooks' => intval($db->GetOneMaster('SELECT count(id) FROM webhook_listener')),
						'workers' => intval($db->GetOneMaster('SELECT count(id) FROM worker')),
						'workers_active_15m' => intval($db->GetOneMaster(sprintf('SELECT count(DISTINCT user_id) AS hits FROM devblocks_session WHERE user_id != 0 AND refreshed_at >= %d', time()-900))),
						'workers_active_30m' => intval($db->GetOneMaster(sprintf('SELECT count(DISTINCT user_id) AS hits FROM devblocks_session WHERE user_id != 0 AND refreshed_at >= %d', time()-1800))),
						'workers_active_1h' => intval($db->GetOneMaster(sprintf('SELECT count(DISTINCT user_id) AS hits FROM devblocks_session WHERE user_id != 0 AND refreshed_at >= %d', time()-3600))),
						'workers_active_24h' => intval($db->GetOneMaster(sprintf('SELECT count(DISTINCT user_id) AS hits FROM devblocks_session WHERE user_id != 0 AND refreshed_at >= %d', time()-86400))),
						'workers_active_1w' => intval($db->GetOneMaster(sprintf('SELECT count(DISTINCT user_id) AS hits FROM devblocks_session WHERE user_id != 0 AND refreshed_at >= %d', time()-604800))),
					),
					'storage_bytes' => array(
						'attachment' => intval($db->GetOneMaster('SELECT sum(storage_size) FROM attachment')),
						'context_avatar' => intval($db->GetOneMaster('SELECT sum(storage_size) FROM context_avatar')),
						'message_content' => intval($db->GetOneMaster('SELECT sum(storage_size) FROM message')),
					)
				);
				
				// Storage
				
				$status['storage_bytes']['_total'] = array_sum($status['storage_bytes']);
				
				// Plugins
				
				$status['plugins'] = array();
				$plugins = DevblocksPlatform::getPluginRegistry();
				unset($plugins['cerberusweb.core']);
				unset($plugins['devblocks.core']);
				$status['counts']['plugins_enabled'] = count($plugins);
				ksort($plugins);
				
				foreach($plugins as $plugin) {
					if($plugin->enabled)
						$status['plugins'][] = $plugin->id;
				}
				
				// Tables
				
				$status['database'] = array(
					'data_bytes' => 0,
					'index_bytes' => 0,
					'data_slack_bytes' => 0,
				);
				@$tables = $db->metaTablesDetailed();
				
				foreach($tables as $table => $info) {
					$status['database']['data_bytes'] += $info['Data_length'];
					$status['database']['index_bytes'] += $info['Index_length'];
					$status['database']['data_slack_bytes'] += $info['Data_free'];
				}
				
				// Output
				
				echo json_encode($status);
				break;
				
			case 'report':
				@$db = DevblocksPlatform::getDatabaseService();
				@$settings = DevblocksPlatform::getPluginSettingsService();
				
				@$tables = $db->metaTablesDetailed();
				
				$report_output = sprintf(
					"[Cerb] App Version: %s\n".
					"[Cerb] App Build: %s\n".
					"[Cerb] Devblocks Build: %s\n".
					"[Cerb] URL-Rewrite: %s\n".
					"\n".
					"[Privs] storage/attachments: %s\n".
					"[Privs] storage/mail/new: %s\n".
					"[Privs] storage/mail/fail: %s\n".
					"[Privs] tmp: %s\n".
					"[Privs] tmp/templates_c: %s\n".
					"[Privs] tmp/cache: %s\n".
					"\n".
					"[PHP] Version: %s\n".
					"[PHP] OS: %s\n".
					"[PHP] SAPI: %s\n".
					"\n".
					"[php.ini] max_execution_time: %s\n".
					"[php.ini] memory_limit: %s\n".
					"[php.ini] file_uploads: %s\n".
					"[php.ini] upload_max_filesize: %s\n".
					"[php.ini] post_max_size: %s\n".
					"[php.ini] safe_mode: %s\n".
					"\n".
					"[PHP:Extension] MySQL: %s\n".
					"[PHP:Extension] MailParse: %s\n".
					"[PHP:Extension] cURL: %s\n".
					"[PHP:Extension] IMAP: %s\n".
					"[PHP:Extension] Session: %s\n".
					"[PHP:Extension] PCRE: %s\n".
					"[PHP:Extension] GD: %s\n".
					"[PHP:Extension] mbstring: %s\n".
					"[PHP:Extension] iconv: %s\n".
					"[PHP:Extension] XML: %s\n".
					"[PHP:Extension] SimpleXML: %s\n".
					"[PHP:Extension] DOM: %s\n".
					"[PHP:Extension] SPL: %s\n".
					"[PHP:Extension] ctype: %s\n".
					"[PHP:Extension] JSON: %s\n".
					"[PHP:Extension] tidy: %s\n".
					"[PHP:Extension] XCache: %s\n".
					"[PHP:Extension] XDebug: %s\n".
					"[PHP:Extension] memcache: %s\n".
					"[PHP:Extension] memcached: %s\n".
					"[PHP:Extension] redis: %s\n".
					"\n",
					APP_VERSION,
					APP_BUILD,
					PLATFORM_BUILD,
					(file_exists(APP_PATH . '/.htaccess') ? 'YES' : 'NO'),
					substr(sprintf('%o', @fileperms(APP_STORAGE_PATH.'/attachments')), -4),
					substr(sprintf('%o', fileperms(APP_STORAGE_PATH.'/mail/new')), -4),
					substr(sprintf('%o', fileperms(APP_STORAGE_PATH.'/mail/fail')), -4),
					substr(sprintf('%o', fileperms(APP_TEMP_PATH)), -4),
					substr(sprintf('%o', fileperms(APP_SMARTY_COMPILE_PATH)), -4),
					substr(sprintf('%o', fileperms(APP_TEMP_PATH.'/cache')), -4),
					PHP_VERSION,
					PHP_OS . ' (' . php_uname() . ')',
					php_sapi_name(),
					ini_get('max_execution_time'),
					ini_get('memory_limit'),
					ini_get('file_uploads'),
					ini_get('upload_max_filesize'),
					ini_get('post_max_size'),
					ini_get('safe_mode'),
					(extension_loaded("mysql") ? 'YES' : 'NO'),
					(extension_loaded("mailparse") ? 'YES' : 'NO'),
					(extension_loaded("curl") ? 'YES' : 'NO'),
					(extension_loaded("imap") ? 'YES' : 'NO'),
					(extension_loaded("session") ? 'YES' : 'NO'),
					(extension_loaded("pcre") ? 'YES' : 'NO'),
					(extension_loaded("gd") ? 'YES' : 'NO'),
					(extension_loaded("mbstring") ? 'YES' : 'NO'),
					(extension_loaded("iconv") ? 'YES' : 'NO'),
					(extension_loaded("xml") ? 'YES' : 'NO'),
					(extension_loaded("simplexml") ? 'YES' : 'NO'),
					(extension_loaded("dom") ? 'YES' : 'NO'),
					(extension_loaded("spl") ? 'YES' : 'NO'),
					(extension_loaded("ctype") ? 'YES' : 'NO'),
					(extension_loaded("json") ? 'YES' : 'NO'),
					(extension_loaded("tidy") ? 'YES' : 'NO'),
					(extension_loaded("xcache") ? 'YES' : 'NO'),
					(extension_loaded("xdebug") ? 'YES' : 'NO'),
					(extension_loaded("memcache") ? 'YES' : 'NO'),
					(extension_loaded("memcached") ? 'YES' : 'NO'),
					(extension_loaded("redis") ? 'YES' : 'NO')
				);
				
				if(!empty($settings)) {
					$report_output .= sprintf(
						"[Setting] HELPDESK_TITLE: %s\n".
						"\n".
						'%s',
						$settings->get('cerberusweb.core',CerberusSettings::HELPDESK_TITLE,''),
						''
					);
				}
				
				if(is_array($tables) && !empty($tables)) {
					$report_output .= sprintf(
						"[Stats] # Workers: %s\n".
						"[Stats] # Groups: %s\n".
						"[Stats] # Tickets: %s\n".
						"[Stats] # Messages: %s\n".
						"\n".
						"[Database] Tables:\n",
						intval($db->GetOneMaster('SELECT count(id) FROM worker')),
						intval($db->GetOneMaster('SELECT count(id) FROM worker_group')),
						intval($db->GetOneMaster('SELECT count(id) FROM ticket')),
						intval($db->GetOneMaster('SELECT count(id) FROM message')),
						''
					);
					
					foreach($tables as $table_name => $table_data) {
						$report_output .= sprintf(" * %s - %s - %d records\n",
							$table_name,
							$table_data['Engine'],
							$table_data['Rows']
						);
					}
					
					$report_output .= "\n";
				}
				
				echo sprintf(
					"<html>
					<head>
						<title></title>
						<style>
							BODY {font-family: Arial, Helvetica, sans-serif; font-size: 12px;}
							FORM {margin:0px; }
							H1 { margin:0px; }
							.fail {color:red;font-weight:bold;}
							.pass {color:green;font-weight:bold;}
						</style>
					</head>
					<body>
						<form>
							<h1>Cerb - Debug Report:</h1>
							<textarea rows='25' cols='100'>%s</textarea>
						</form>
					</body>
					</html>
					",
				$report_output
				);
				
				break;
				
			case 'export_attendants':
				$event_mfts = DevblocksPlatform::getExtensions('devblocks.event', false, true);

				header("Content-type: application/json");
				
				$output = array(
					'virtual_attendants' => array(),
				);
				
				$vas = DAO_VirtualAttendant::getAll();
				
				foreach($vas as $va) {
					$output['virtual_attendants'][$va->id] = array(
						'label' => $va->name,
						'owner_context' => $va->owner_context,
						'owner_context_id' => $va->owner_context_id,
						'behaviors' => array(),
					);
					
					$behaviors = $va->getBehaviors(null, true);
					
					foreach($behaviors as $behavior) {
						if(false !== ($json = $behavior->exportToJson())) {
							$json_array = json_decode($json, true);
							$output['virtual_attendants'][$va->id]['behaviors'][] = $json_array;
						}
					}
				}
				
				echo DevblocksPlatform::strFormatJson(json_encode($output));
				break;
				
			default:
				$url_service = DevblocksPlatform::getUrlService();
				
				echo sprintf(
					"<html>
					<head>
						<title></title>
						<style>
							BODY {font-family: Arial, Helvetica, sans-serif; font-size: 12px;}
							FORM {margin:0px; }
							H1 { margin:0px; }
						</style>
					</head>
					<body>
						<form>
							<h1>Cerb - Debug Menu:</h1>
							<ul>
								<li><a href='%s'>Requirements Checker</a></li>
								<li><a href='%s'>Debug Report (for technical support)</a></li>
								<li><a href='%s'>phpinfo()</a></li>
								<li><a href='%s'>Export Virtual Attendants</a></li>
							</ul>
						</form>
					</body>
					</html>
					"
					,
					$url_service->write('c=debug&a=check'),
					$url_service->write('c=debug&a=report'),
					$url_service->write('c=debug&a=phpinfo'),
					$url_service->write('c=debug&a=export_attendants')
				);
				break;
		}
		
		exit;
	}
};
