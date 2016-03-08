<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// Remove the worker last activity fields

if(!isset($tables['worker'])) {
	$logger->error("The 'worker' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('worker');

if(isset($columns['last_activity'])) {
	$db->ExecuteMaster("ALTER TABLE worker DROP COLUMN last_activity");
}

if(isset($columns['last_activity_date'])) {
	$db->ExecuteMaster("ALTER TABLE worker DROP COLUMN last_activity_date");
}

if(isset($columns['last_activity_ip'])) {
	$db->ExecuteMaster("ALTER TABLE worker DROP COLUMN last_activity_ip");
}

// ===========================================================================
// Remove the old _version file if it exists

if(file_exists(APP_STORAGE_PATH . '/_version'))
	@unlink(APP_STORAGE_PATH . '/_version');

// ===========================================================================
// Convert `message_header` to `message_headers`

if(!isset($tables['message'])) {
	$logger->error("The 'message' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('message');

$changes = array();

if(!isset($columns['hash_header_message_id']))
	$changes[] = 'add column hash_header_message_id varchar(40)';

if(isset($indexes['storage_extension']))
	$changes[] = 'drop index storage_extension';

if(isset($indexes['storage_profile_id']))
	$changes[] = 'drop index storage_profile_id';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE message %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}

if(!isset($tables['message_headers'])) {
	$sql = sprintf("
		CREATE TABLE `message_headers` (
			`message_id` int unsigned not null default 0,
			`headers` text,
			primary key (message_id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());

	$tables['message_headers'] = 'message_headers';
}

if(isset($tables['message_header'])) {
	$id_max = $db->GetOneMaster('SELECT max(id) from message');

	if(false === $id_max)
		die("[MySQL Error] " . $db->ErrorMsgMaster());
	
	$id_from = 0;
	$id_to = 0;
	
	$db->GetOneMaster('SET group_concat_max_len = 1024000');
	
	while($id_from < $id_max) {
		$id_to = $id_from + 9999;
		
		// Move message-id header hashes to the message table
		$sql = sprintf("UPDATE message SET hash_header_message_id = (SELECT sha1(message_header.header_value) FROM message_header WHERE message_header.message_id=message.id AND message_header.header_name = 'message-id' LIMIT 1) WHERE message.id BETWEEN %d and %d", $id_from, $id_to);
	  if(false === ($db->ExecuteMaster($sql))) {
	  	die("[MySQL Error] " . $db->ErrorMsgMaster());
	  }
		
	  // Move all the headers for a single message_id into a single blob
	  $sql = sprintf("INSERT IGNORE INTO message_headers (message_id, headers) SELECT message_id, GROUP_CONCAT(header_name, ': ', REPLACE(header_value, '\r\n', '\r\n\t') separator '\r\n') AS headers FROM message_header WHERE message_id BETWEEN %d and %d GROUP BY message_id", $id_from, $id_to);
		if(false === ($db->ExecuteMaster($sql))) {
	  	die("[MySQL Error] " . $db->ErrorMsgMaster());
		}
		
		$id_from = $id_to + 1;
	}
	
	if(!isset($tables['message'])) {
		$logger->error("The 'message' table does not exist.");
		return FALSE;
	}
	
	list($columns, $indexes) = $db->metaTable('message');
	
	if(!isset($indexes['hash_header_message_id']))
		$db->ExecuteMaster("ALTER TABLE message ADD INDEX hash_header_message_id (hash_header_message_id(4))") or die("[MySQL Error] " . $db->ErrorMsgMaster());

	if(isset($tables['message_header'])) {
		$db->ExecuteMaster("DROP TABLE message_header") or die("[MySQL Error] " . $db->ErrorMsgMaster());
		unset($tables['message_header']);
	}
}

// ===========================================================================
// Optimize address indexes

if(!isset($tables['address'])) {
	$logger->error("The 'address' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('address');

$changes = array();

if(isset($indexes['num_spam']))
	$changes[] = 'drop index num_spam';

if(isset($indexes['num_nonspam']))
	$changes[] = 'drop index num_nonspam';

if(isset($indexes['is_banned']))
	$changes[] = 'drop index is_banned';

if(isset($indexes['is_defunct']))
	$changes[] = 'drop index is_defunct';

if(!isset($indexes['banned_and_defunct']))
	$changes[] = 'add index banned_and_defunct (is_banned, is_defunct)';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE address  %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}

// ===========================================================================
// Optimize attachment_link indexes

if(!isset($tables['attachment_link'])) {
	$logger->error("The 'attachment_link' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('attachment_link');

$changes = array();

if(isset($indexes['context']))
	$changes[] = 'drop index context';

if(isset($indexes['context_id']))
	$changes[] = 'drop index context_id';

if(!isset($indexes['context_and_id']))
	$changes[] = 'add index context_and_id (context, context_id)';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE attachment_link %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}

// ===========================================================================
// Optimize comment indexes

if(!isset($tables['comment'])) {
	$logger->error("The 'comment' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('comment');

$changes = array();

if(isset($indexes['context']))
	$changes[] = 'drop index context';

if(isset($indexes['context_id']))
	$changes[] = 'drop index context_id';

if(!isset($indexes['context_and_id']))
	$changes[] = 'add index context_and_id (context, context_id)';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE comment %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}

// ===========================================================================
// Optimize context_link indexes

if(!isset($tables['context_link'])) {
	$logger->error("The 'context_link' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('context_link');

$changes = array();

if(isset($indexes['from_context']))
	$changes[] = 'drop index from_context';

if(isset($indexes['from_context_id']))
	$changes[] = 'drop index from_context_id';

if(!isset($indexes['from_context_and_id']))
	$changes[] = 'add index from_context_and_id (from_context, from_context_id)';

if(isset($indexes['to_context']))
	$changes[] = 'drop index to_context';

if(isset($indexes['to_context_id']))
	$changes[] = 'drop index to_context_id';

if(!isset($indexes['to_context_and_id']))
	$changes[] = 'add index to_context_and_id (to_context, to_context_id)';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE context_link %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}

// ===========================================================================
// Consolidate ticket status fields

if(!isset($tables['ticket'])) {
	$logger->error("The 'ticket' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('ticket');

$changes = array();

if(!isset($columns['status_id']))
	$changes[] = 'add column status_id tinyint unsigned not null default 0';

if(isset($indexes['mask']) && 3 != $indexes['mask']['columns']['mask']['subpart'])
	$changes[] = 'drop index mask, add index mask (mask(3))';

if(isset($indexes['first_wrote_address_id']))
	$changes[] = 'drop index first_wrote_address_id';

if(isset($indexes['last_wrote_address_id']))
	$changes[] = 'drop index last_wrote_address_id';

if(isset($indexes['first_message_id']))
	$changes[] = 'drop index first_message_id';

if(isset($indexes['last_message_id']))
	$changes[] = 'drop index last_message_id';

if(isset($indexes['last_action_code']))
	$changes[] = 'drop index last_action_code';

if(isset($indexes['spam_score']))
	$changes[] = 'drop index spam_score';

if(isset($indexes['importance']))
	$changes[] = 'drop index importance';

if(isset($indexes['team_id']))
	$changes[] = 'drop index team_id';

if(isset($indexes['category_id']))
	$changes[] = 'drop index category_id';

if(!isset($indexes['group_and_bucket']))
	$changes[] = 'add index group_and_bucket (group_id, bucket_id)';

if(!isset($indexes['bucket_id']))
	$changes[] = 'add index (bucket_id)';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE ticket %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}

if(isset($columns['is_waiting']) && isset($columns['is_closed']) && isset($columns['is_deleted'])) {
	$db->ExecuteMaster("UPDATE ticket SET status_id = 3 WHERE is_deleted = 1 AND status_id = 0");
	$db->ExecuteMaster("UPDATE ticket SET status_id = 2 WHERE is_closed = 1 AND  status_id = 0");
	$db->ExecuteMaster("UPDATE ticket SET status_id = 1 WHERE is_waiting = 1 AND  status_id = 0");
	$db->ExecuteMaster("ALTER TABLE ticket DROP COLUMN is_waiting, DROP COLUMN is_closed, DROP COLUMN is_deleted, ADD INDEX status_and_group (status_id, group_id)");
}

// ===========================================================================
// Migrate mail_queue draft records to the new status bits

$status_map = array(0,2,1,3);

$rs = $db->ExecuteMaster("SELECT id, params_json FROM mail_queue WHERE params_json LIKE '%closed%'");

if($rs instanceof mysqli_result) {
	while($row = mysqli_fetch_assoc($rs)) {
		if(false == ($json = json_decode($row['params_json'], true)))
			continue;
		
		if(isset($json['closed'])) {
			$json['status_id'] = (string) $status_map[intval($json['closed'])];
			unset($json['closed']);
			
			$sql = sprintf("UPDATE mail_queue SET params_json = %s WHERE id = %d",
				$db->qstr(json_encode($json)),
				$row['id']
			);
			$db->ExecuteMaster($sql);
		}
	}
	mysqli_free_result($rs);
}

// ===========================================================================
// Migrate VAs to the new status bits

$status_map = array(0,2,1,3);

$rs = $db->ExecuteMaster("SELECT id, params_json FROM decision_node WHERE node_type = 'action' AND params_json LIKE '%%create_ticket%%'");

if($rs instanceof mysqli_result) {
	while($row = mysqli_fetch_assoc($rs)) {
		if(false == ($json = json_decode($row['params_json'], true)))
			continue;
		
		$is_changed = false;
		
		if(isset($json['actions']))
		foreach($json['actions'] as &$action) {
			if(isset($action['status'])) {
				$action['status'] = (string) $status_map[intval($action['status'])];
				$is_changed = true;
			}
		}
		
		if($is_changed) {
			$sql = sprintf(sprintf("UPDATE decision_node SET params_json = %s WHERE id = %d",
				$db->qstr(json_encode($json)),
				$row['id']
			));
			$db->ExecuteMaster($sql);
		}
	}
	
	mysqli_free_result($rs);
}

// ===========================================================================
// Finish up

return TRUE;