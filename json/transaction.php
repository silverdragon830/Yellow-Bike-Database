<?php

require_once('../Connections/database_functions.php');
require_once('../Connections/YBDB.php');
mysql_select_db($database_YBDB, $YBDB);

/*
require_once('../php-console/src/PhpConsole/__autoload.php');
$handler = PhpConsole\Handler::getInstance();
$handler->start();
*/

$change_fund = CHANGE_FUND;
$csv_directory = CSV_DIRECTORY;
$stand_time_hourly_rate = STAND_TIME_HOURLY_RATE;
$stand_time_grace_period = STAND_TIME_GRACE_PERIOD;
$timezone = TIMEZONE;
$sweat_equity_limit = SWEAT_EQUITY_LIMIT;
$max_bike_earned = MAX_BIKE_EARNED;
$volunteer_hour_value = VOLUNTEER_HOUR_VALUE;

	// Is there a current shop?
	if(isset($_POST['shop_exist'])) {
		if(current_shop_by_ip()>=1) { 
			echo "current_shop"; 
		} else {
			echo "no_shop";
		}
	}	
	
	// update whether paid or not
	if(isset($_POST['paid'])) {
			if ($_POST['paid'] == 1) {			
			
				$query = "UPDATE transaction_log SET paid=1 WHERE transaction_id=" . $_POST['transaction_id'] . ";";				 
				$result = mysql_query($query, $YBDB) or die(mysql_error());
		
			} elseif($_POST['paid'] == 0) {
	  			
			  	$query = "UPDATE transaction_log SET paid=0 WHERE transaction_id=" . $_POST['transaction_id'] . ";";
			  	$result = mysql_query($query, $YBDB) or die(mysql_error());	    
		
			}
	  }

	// update payment type
	if(isset($_POST['payment_type'])) {
		
		$query = 'UPDATE transaction_log SET payment_type="' . 
					$_POST['payment_type'] . '" WHERE transaction_id=' . 
					$_POST['transaction_id'] . ";";				 
		$result = mysql_query($query, $YBDB) or die(mysql_error());
		
	}

	// If payment_type check is selected - return check number if exists 
	if (isset($_POST['check_number'])) {
		
			$query = 'SELECT check_number FROM transaction_log WHERE transaction_id="' . $_POST['transaction_id'] . '";';
			$sql = mysql_query($query, $YBDB) or die(mysql_error());
			$result = mysql_fetch_assoc($sql);
			echo json_encode($result);		
		
	}

	// Editable Change Fund
	if(isset($_POST['editable_change'])) {
		
		$transaction_id = split('_', $_POST['id'], 1);
		$query = 'UPDATE transaction_log set change_fund="' . $_POST['editable_change'] . '" WHERE transaction_id="' . $transaction_id[0] . '";';
		$result = mysql_query($query, $YBDB) or die(mysql_error());
		$send_back = array(
			"changed_change" => $_POST['editable_change'], 
			"change" => $change_fund,
		);
		echo json_encode($send_back);	
	}

	// Patron who made a transaction not logged in.
	if (isset($_POST['not_logged_in'])) {
		$query = "SELECT CONCAT(contacts.last_name, ', ', contacts.first_name, ' ',contacts.middle_initial) AS full_name, 
					transaction_log.sold_to
					FROM transaction_log, contacts 
					WHERE transaction_id=" . $_POST['transaction_id'] .
					" AND contacts.contact_id = transaction_log.sold_to;";
		 $sql = mysql_query($query, $YBDB) or die(mysql_error());
		 $result = mysql_fetch_assoc($sql);
		 echo json_encode($result);		
	}

	// Membership Benefits
	if (isset($_POST['membership_benefits'])) {
		
		echo json_encode(membership_benefits($_POST['contact_id']));

	} // Membership Benefits
	
	// Volunteer Benefits
	if (isset($_POST['volunteer_benefits'])) {
		
		$query = "SELECT contact_id, full_name, normal_full_name, email, phone, visits, volunteer_hours, sweat_equity_redeemed, max_bike_earned, year 
					FROM (SELECT contacts.contact_id, 
					CONCAT(last_name, ', ', first_name, ' ',middle_initial) AS full_name, 
					CONCAT(first_name, ' ', last_name) AS normal_full_name, 
					contacts.email AS email, 
					contacts.phone AS phone,  
					COUNT(shop_hours.contact_id) AS visits, 
					contacts.sweat_equity_redeemed AS sweat_equity_redeemed,
					contacts.max_bike_earned AS max_bike_earned,
					contacts.year AS year, 
					ROUND(SUM(HOUR(SUBTIME( TIME(time_out), TIME(time_in))) + MINUTE(SUBTIME( TIME(time_out), TIME(time_in)))/60)) AS volunteer_hours  
					FROM shop_hours  
					LEFT JOIN contacts ON shop_hours.contact_id = contacts.contact_id  
					LEFT JOIN shop_user_roles ON shop_hours.shop_user_role = shop_user_roles.shop_user_role_id  
					WHERE  (SUBSTRING_INDEX(time_in, ' ', 1) >= DATE_SUB(CURDATE(),INTERVAL 365 DAY)   
					AND SUBSTRING_INDEX(time_in, ' ', 1) <= DATE_SUB(CURDATE(), INTERVAL 0 DAY)) 
					AND shop_user_roles.volunteer = 1 AND contacts.contact_id=" .
					$_POST['contact_id'] . 
					" GROUP BY contact_id) AS members;";	
					
		$sql = mysql_query($query, $YBDB) or die(mysql_error());
		$result = mysql_fetch_assoc($sql);
				
		// current year volunteer hours and visits
		$query =  "SELECT current_year_visits, current_year_volunteer_hours 
					 FROM (SELECT contacts.contact_id, CONCAT(last_name, ', ', first_name, ' ',middle_initial) AS full_name, 
					 CONCAT(first_name, ' ', last_name) AS normal_full_name, contacts.email AS email, 
					 contacts.phone AS phone, COUNT(shop_hours.contact_id) AS current_year_visits, 
					 ROUND(SUM(HOUR(SUBTIME( TIME(time_out), TIME(time_in))) + MINUTE(SUBTIME( TIME(time_out), TIME(time_in)))/60)) AS current_year_volunteer_hours 
					 FROM shop_hours 
					 LEFT JOIN contacts ON shop_hours.contact_id = contacts.contact_id 
					 LEFT JOIN shop_user_roles ON shop_hours.shop_user_role = shop_user_roles.shop_user_role_id 
					 WHERE  (SUBSTRING_INDEX(time_in, ' ', 1) >= CONCAT(YEAR(CURDATE()),'-01-01') 
					 AND SUBSTRING_INDEX(time_in, ' ', 1) <= DATE_ADD(CONCAT(YEAR(CURDATE()),'-01-01'),INTERVAL DAYOFYEAR(CONCAT(YEAR(CURDATE()),'-12-31')) - 1 DAY)) 
					 AND shop_user_roles.volunteer = 1 
					 AND contacts.contact_id=" . 
					 $_POST['contact_id'] .
					 ") AS members;";

		$sql = mysql_query($query, $YBDB) or die(mysql_error());
		$result2 = mysql_fetch_assoc($sql);
		
		
		
		// update volunteer_benefits  either on sold_to.change (initialize) or redeem.change in separate callback
	   // zero out if new year

		//$handler->debug($result["sweat_equity_redeemed"]);
		
		if($result["sweat_equity_redeemed"] == 0) {
			$redeemable_hours_amount = $result2["current_year_volunteer_hours"] * $volunteer_hour_value;
			$remaining_redeemable_hours_amount_available = $sweat_equity_limit - $redeemable_hours_amount;
		} else {
			$remaining_redeemable_hours_amount_available = $sweat_equity_limit - $result["sweat_equity_redeemed"];
		}
				
		$result3["max_bike_earned"] = $max_bike_earned;
		if($remaining_redeemable_hours_amount_available >= 0) {
			//$handler->debug("$redeemable_hours_amount $remaining_redeemable_hours_amount_available");
			$result3["redeemable_hours"] = $redeemable_hours_amount;
		} else {
			$result3["redeemable_hours"] = $sweat_equity_limit;
		}

		$result = (object)array_merge((array)$result, (array)$result2, (array)$result3);
		echo json_encode($result);

	}	// Volunteer Benefits

	// Stand Time
	if (isset($_POST['stand_time'])) {
		
		
		// use the most recent login status; can do calculations here
		// ROUND(SUM(HOUR(SUBTIME( TIME(time_out), TIME(time_in))) + MINUTE(SUBTIME( TIME(time_out), TIME(time_in)))/60))
		// SUBSTRING_INDEX(time_in, ' ', 1)
		$query = "SELECT time_in, time_out FROM shop_hours WHERE shop_id=" . $_POST['shop_id'] . 
					" AND shop_user_role='Stand Time' 
					AND contact_id=" . $_POST['contact_id'] . ";";		
		
		$sql = mysql_query($query, $YBDB) or die(mysql_error());
		$result = mysql_fetch_assoc($sql);
		 
		// need to factor in for more than one login in same shop to add up all minutes
		// and check for sign_out and calculate that if it exists.
		
		$time_ins = [];
		$sql = mysql_query($query, $YBDB) or die(mysql_error());
		while ( $results = mysql_fetch_assoc($sql) ) {

					
			
			$sign_in = new DateTime($results['time_in'], new DateTimeZone($timezone));
			$sign_out = new DateTime("", new DateTimeZone($timezone));

			// signed-out
			if($results['time_out'] != '0000-00-00 00:00:00') {
				$sign_out = new DateTime($results['time_out'], new DateTimeZone($timezone));
				$time_ins[] = $sign_out->diff($sign_in);	
			
			// signed-in	
			} else {		
		 		$time_ins[] = $sign_out->diff($sign_in);
		 	}		
		} 

		if ($time_ins) {
					 
			foreach ( $time_ins as $key => $value ) {			
				$total_minutes += ($value->h * 60) + $value->i;
				$stand_time_remainder += $total_minutes % 60;
				$hour += $value->h;
				$minute += $value->i;
			}
	
			$time = ($hour * 60) + $minute;
			$hours = floor($time / 60);
		   $minutes = ($time % 60);
	 
			//$handler->debug("$total_minutes $stand_time_remainder $hours $minutes $hours $minutes");
			 				
			if($total_minutes != $stand_time_remainder && $stand_time_remainder <= $stand_time_grace_period) { // still within grace period
				$answer = "within grace period $hours $minutes";	
				$stand_time = $hours;				
			} elseif($total_minutes != $stand_time_remainder && $stand_time_remainder > $stand_time_grace_period) { // outside grace period
				$answer = "outside grace period $hours $minutes";
				$stand_time = $hours + 1;	
			} elseif($total_minutes == $stand_time_remainder && $minutes > $stand_time_grace_period) {  // 1hr or less outside grace period
				$answer = "1hr or less $hours $minutes";
				$stand_time = 1;
			} elseif($total_minutes <= $stand_time_grace_period) { // first hour still within grace period
				$answer = "less than 1hr and within grace period $hours $minutes";
				$stand_time = $hours;
			} 			
	
			$total = $stand_time * $stand_time_hourly_rate;	
			$stand_time_array = array("total" => $total, "hours" => $hours, "minutes" => $minutes, "answer" => $answer);
			if(membership_benefits($_POST['contact_id'])) {
				$stand_time_array["membership"] = true;
			}
			echo json_encode($stand_time_array);		 
		 
		} // end if time_ins

	} // Stand Time
	
	// Anonymous transaction - save and communicate back settings
	if(isset($_POST['anonymous'])) {
		
		if ($_POST['anonymous'] == 1) {
			$query = 'UPDATE transaction_log SET anonymous=1, sold_to=NULL WHERE transaction_id="' . 
						$_POST['transaction_id'] . '";';
			$result = mysql_query($query, $YBDB) or die(mysql_error());
		} else {
			$query = 'UPDATE transaction_log SET anonymous=0 WHERE transaction_id="' . 
						$_POST['transaction_id'] . '";';
			$result = mysql_query($query, $YBDB) or die(mysql_error());		
		}
	
	} 

	// Transaction history - fetch history
	if(isset($_POST['history_select'])) {
		$query = 'SELECT history FROM transaction_log WHERE transaction_id="' . $_POST['transaction_id'] . '";';
		$sql = mysql_query($query, $YBDB) or die(mysql_error());	
		$result = mysql_fetch_assoc($sql);			
		if ($result['history'] == "") {
			echo "First Transaction";		
		} else {
			// Description may have newlines			
			$history_result = str_replace("\n", "\\n",$result['history']);
			echo $history_result;		
		}
	}
	
	// Transaction history - update transaction history
	// Note:  This could easily be turned into its own table with a foreign key
	// referencing transaction_log.transaction_id, but most transactions
	// will probably only occur 1 time, and there probably isn't that much
	// need to do many things with this data other than rollback a transaction, or
	// research what happened on a particular shop day.
	if(isset($_POST['history_update'])) {
		$json = json_encode($_POST['history']);
		$query = "UPDATE transaction_log SET history='$json'" .  
					' WHERE transaction_id="' . $_POST['transaction_id'] . '";';
		$result = mysql_query($query, $YBDB) or die(mysql_error());	

		// show history
		if(isset($_POST['more_than_one'])) {
			list_history($_POST['history']);
		}
	}

	// Check for most recent transaction_id if transaction_id has changed
	if(isset($_POST['most_recent_transaction_id'])) {
		$query = 'SELECT MAX(transaction_id) as transaction_id FROM transaction_log;';
		$sql = mysql_query($query, $YBDB) or die(mysql_error());	
		$result = mysql_fetch_assoc($sql);	
		echo $result['transaction_id'];	
	}	

	// check if start storage date has been changed since original shop date
	if(isset($_POST['date_startstorage'])) {
		$query = 'SELECT shops.date FROM transaction_log, shops WHERE transaction_id=' . $_POST['transaction_id'] .
					 ' AND transaction_log.shop_id = shops.shop_id;';
		$sql = mysql_query($query, $YBDB) or die(mysql_error());	
		$result = mysql_fetch_assoc($sql);	
		if ($result['date'] != $_POST['date_startstorage']) {
			echo $result['date'];		
		}
	}

	// reset payment_type && amount for storage transaction
	if(isset($_POST['storage_payment_reset'])) {
		
		$query = 'UPDATE transaction_log SET payment_type=NULL, amount=NULL WHERE transaction_id="' . 
					$_POST['transaction_id'] . '";';
		$result = mysql_query($query, $YBDB) or die(mysql_error());
	}

	// populate transaction slider for accounting programs
	
	// Originally, deposits of $0 (amount > 0) would not be considered real deposits, however,
	// there may be shops where only non-monetary transactions occurred (amount >= 0)
	// which would be useful to record in an accounting program.
	// One caveat, if a monetary transaction is recorded, but the depositor only
	// enters $0, the deposit will show "Difference: n/a", however this should be a cue
	// since it should be obvious that a real world deposit of $0
	// would not be made at a bank.
	if (isset($_POST['transaction_slider'])) {
		$query = 'SELECT transaction_id, IF(amount >= 0, "yes", "no") AS "deposited", date 
					FROM transaction_log WHERE transaction_type= "Deposit";';
		$sql = mysql_query($query, $YBDB) or die(mysql_error());
		while ( $result = mysql_fetch_assoc($sql) ) {
					$slider_range[] = $result;					
		}
		
		// this is the first real deposit
		if ( ($slider_range && !$slider_range[1]["transaction_id"]) || ($slider_range && $slider_range[1]["deposited"] == "no") ) { 
			$fake_trans_id = 0;
			$real_trans = $slider_range[0];
			$year = date("Y");
			$slider_range[0] = array("transaction_id" => "$fake_trans_id","deposited" => "yes","date" => "$year-01-01 22:22:22");
			$slider_range[1] = $real_trans;
			echo json_encode($slider_range);
		
		// no real deposits exist				
		} elseif (!$slider_range) {
		// send fake data
			$year = date("Y");
			$slider_range = array
							(	array("transaction_id" => "0","deposited" => "yes","date" => "$year-01-01 22:22:22"),
								array("transaction_id" => "1","deposited" => "yes","date" => "$year-01-02 22:22:22"),
							);
			echo json_encode($slider_range);
		
		// more than 1 deposit exists		
		} else {
			echo json_encode($slider_range);
		}
		
	}
	
	// Create csv file(s) for GnuCash
	if(isset($_POST['gnucash_account_type'])) {	
	
		$transaction_range = $_POST['transaction_range'];	
		$account_type = $_POST['gnucash_account_type'];	
		$accounts_gnucash = array_flip($gnucash_accounts);

						
		// Date (yyyy-mm-dd), Num, Description, Deposit, Account		
		
		// checking (check or cash) || credit
		// transaction has been 1) paid and is 2) cash & check [checking] or credit and 3) deposited
		if( $account_type === 'checking' ) {

			// first statement to find coordinator for associated transactions	
			$query = "SELECT transaction_id, " .
						"CONCAT(contacts.first_name, ' ', contacts.last_name) AS 'coordinator' " .
						"FROM transaction_log, contacts WHERE paid=1 AND date!='NULL' " .
						"AND (payment_type='cash' OR payment_type='check') " . 
						"AND contacts.contact_id = transaction_log.sold_by " .
						"AND (transaction_id>" . $transaction_range[0] . " AND transaction_id<" . $transaction_range[1]  . ");";		
			$sql2 = mysql_query($query, $YBDB) or die(mysql_error());
			$coordinator = [];
			while ( $result = mysql_fetch_assoc($sql2) ) {
				$coordinator[$result['transaction_id']] = $result['coordinator']; 
			}				
			
			// second statement to find normal transactions
			$query = "SELECT SUBSTRING_INDEX(date, ' ', 1) AS 'date', transaction_id, transaction_type, check_number, description, amount, " .
						"CONCAT(contacts.first_name, ' ', contacts.last_name) AS 'patron' " .
						"FROM transaction_log, contacts WHERE paid=1 AND date!='NULL' " .
						"AND (payment_type='cash' OR payment_type='check') " . 
						"AND contacts.contact_id = transaction_log.sold_to " .
						"AND (transaction_id>" . $transaction_range[0] . " AND transaction_id<" . $transaction_range[1]  . ");";		
			$sql2 = mysql_query($query, $YBDB) or die(mysql_error());
							
			// third statement to find anonymous transactions			
			$query = "SELECT SUBSTRING_INDEX(date, ' ', 1) AS 'date', transaction_id, transaction_type, check_number, description, amount " .
						"FROM transaction_log WHERE paid=1 AND date!='NULL' " .
						"AND  (payment_type='cash' OR payment_type='check') " . 
						"AND  anonymous=1 " .
						"AND (transaction_id>" . $transaction_range[0] . " AND transaction_id<" . $transaction_range[1]  . ");";				
			$sql3 = mysql_query($query, $YBDB) or die(mysql_error());	
			
			$gnucash_csv_file = "";
			
			// normal transaction
			while ( $result = mysql_fetch_assoc($sql2) ) {
				$description = preg_replace('/\n/', ' \r ', $result['description']);
				$description = preg_replace('/\r/', '\r', $description);
				$description = preg_replace('/,/', ';', $description);
				if ($result['check_number'] != "NULL") {
					$check_number = $result['check_number'];			
				}
				$gnucash_csv_file .= $result['date'] . ', ' . $result['transaction_id'] . ' ' . $check_number . 
											',' . ' [' . $coordinator[$result['transaction_id']] . ' => ' . $result['patron'] . '] ' .  
											$description . ' (Income:' . $result['transaction_type'] . ') '  . 
											', ' . $result['amount'] . ', ' . 
											$accounts_gnucash['checking'] . "\n";
			}	

			// anonymous transaction
			while ( $result = mysql_fetch_assoc($sql3) ) {
				$description = preg_replace('/\n/', ' \r ', $result['description']);
				$description = preg_replace('/\r/', '\r', $description);
				$description = preg_replace('/,/', ';', $description);
				if ($result['check_number'] != "NULL") {
					$check_number = $result['check_number'];			
				}
				$gnucash_csv_file .= $result['date'] . ', ' . $result['transaction_id'] . ' ' . $check_number .
											',' . ' [' . $coordinator[$result['transaction_id']] . ' => Anonymous] ' .  
											$description . ' (Income:' . $result['transaction_type'] . ') '  . 
											', ' . $result['amount'] . ', ' . 
											$accounts_gnucash['checking'] . "\n";						
			}
			
			$file_name = preg_replace('/ /', '_', $accounts_gnucash['checking']);
			$file_name = preg_replace('/:/', '-', $file_name);
			$file_name = $file_name . '-' . $transaction_range[0] . '-' . $transaction_range[1] . '.csv';
			$file = '../' . $csv_directory . '/' . $file_name;
			$csv_file = fopen($file, "w") or die("Unable to open file for writing.");
			fwrite($csv_file, $gnucash_csv_file);
			fclose($csv_file);
	
			echo $file;		
		}

		if ( $account_type === 'credit' ) {
			
			// first statement to find coordinator for associated transactions	
			$query = "SELECT transaction_id, " .
						"CONCAT(contacts.first_name, ' ', contacts.last_name) AS 'coordinator' " .
						"FROM transaction_log, contacts WHERE paid=1 AND date!='NULL' " .
						"AND payment_type='credit' " . 
						"AND contacts.contact_id = transaction_log.sold_by " .
						"AND (transaction_id>" . $transaction_range[0] . " AND transaction_id<" . $transaction_range[1]  . ");";		
			$sql = mysql_query($query, $YBDB) or die(mysql_error());
			$coordinator = [];
			while ( $result = mysql_fetch_assoc($sql) ) {
				$coordinator[$result['transaction_id']] = $result['coordinator']; 
			}

			// second statement to find normal transactions
			$query = "SELECT SUBSTRING_INDEX(date, ' ', 1) AS 'date', transaction_id, transaction_type, description, amount, " .
						"CONCAT(contacts.first_name, ' ', contacts.last_name) AS 'patron' " .
						"FROM transaction_log, contacts WHERE paid=1 AND date!='NULL' " .
						"AND  payment_type='credit'  " . 
						"AND contacts.contact_id = transaction_log.sold_to " .
						"AND (transaction_id>" . $transaction_range[0] . " AND transaction_id<" . $transaction_range[1]  . ");";		
			$sql2 = mysql_query($query, $YBDB) or die(mysql_error());
			
			// third statement to find anonymous transactions			
			$query = "SELECT SUBSTRING_INDEX(date, ' ', 1) AS 'date', transaction_id, transaction_type, description, amount " .
						"FROM transaction_log WHERE paid=1 AND date!='NULL' " .
						"AND  payment_type='credit'  " . 
						"AND  anonymous=1 " .
						"AND (transaction_id>" . $transaction_range[0] . " AND transaction_id<" . $transaction_range[1]  . ");";				
			$sql3 = mysql_query($query, $YBDB) or die(mysql_error());		
				
			$gnucash_csv_file = "";
			
			// normal transaction	
			while ( $result = mysql_fetch_assoc($sql2) ) {
				$description = preg_replace('/\n/', ' \r ', $result['description']);
				$description = preg_replace('/\r/', '\r', $description);
				$description = preg_replace('/,/', ';', $description);
				$gnucash_csv_file .= $result['date'] . ', ' . $result['transaction_id'] . 
											',' . ' [' . $coordinator[$result['transaction_id']] . ' => ' . $result['patron'] . '] ' .  
											$description . ' (Income:' . $result['transaction_type'] . ') '  . 
											', ' . $result['amount'] . ', ' . 
											$accounts_gnucash['credit'] . "\n";						
			}
			
			// anonymous transaction
			while ( $result = mysql_fetch_assoc($sql3) ) {
				$description = preg_replace('/\n/', ' \r ', $result['description']);
				$description = preg_replace('/\r/', '\r', $description);
				$description = preg_replace('/,/', ';', $description);
				$gnucash_csv_file .= $result['date'] . ', ' . $result['transaction_id'] . 
											',' . ' [' . $coordinator[$result['transaction_id']] . ' => Anonymous] ' .  
											$description . ' (Income:' . $result['transaction_type'] . ') '  . 
											', ' . $result['amount'] . ', ' . 
											$accounts_gnucash['credit'] . "\n";						
			}
				
			$file_name = preg_replace('/ /', '_', $accounts_gnucash['credit']);
			$file_name = preg_replace('/:/', '-', $file_name);
			$file_name = $file_name . '-' . $transaction_range[0] . '-' . $transaction_range[1] . '.csv';
			$file = '../' . $csv_directory . '/' . $file_name;
			$csv_file = fopen($file, "w") or die("Unable to open file for writing.");
			fwrite($csv_file, $gnucash_csv_file);
			fclose($csv_file);

			echo $file;		
		
		}		
		
	} // Create csv file(s) for GnuCash

	// Deposit Calculator
	if (isset($_POST['deposit'])) {
		
		$visible_count = count($_POST['deposit']);
		$c = $visible_count - 1;		
		$deposit = $_POST['deposit'];

		$query = 'SELECT COUNT(transaction_type) AS "count" FROM transaction_log WHERE transaction_type="Deposit";';
		$sql = mysql_query($query, $YBDB) or die(mysql_error());
		$result = mysql_fetch_assoc($sql);
			
		
		if ( $visible_count == $result["count"] ) { // 1 or more deposits, and all deposits are visible	
			
			foreach ( $deposit as $key => $value ) {
							
				if ( $c > $key ) {				
					$query = 'SELECT  SUM(IF(payment_type="check", amount, 0)) AS "check",  
				    			SUM(IF(payment_type="credit", amount, 0)) AS "credit",  
				    			SUM(IF(payment_type="cash", amount, 0)) AS "cash"  
				    			FROM transaction_log WHERE paid=1 AND transaction_id <' . $deposit[$key] . ' AND transaction_id >' 
				    			. $deposit[$key + 1] . ';';
					$sql = mysql_query($query, $YBDB) or die(mysql_error());
					$result = mysql_fetch_assoc($sql);
					$result_obj[$deposit[$key]] = $result; 
				} else { 
					$query = 'SELECT  SUM(IF(payment_type="check", amount, 0)) AS "check",  
							   			SUM(IF(payment_type="credit", amount, 0)) AS "credit",  
							   			SUM(IF(payment_type="cash", amount, 0)) AS "cash"
											FROM transaction_log WHERE paid=1 AND transaction_id <' . $deposit[$key] . ';'; 
					$sql = mysql_query($query, $YBDB) or die(mysql_error());
					$result = mysql_fetch_assoc($sql);				
					$result_obj[$deposit[$key]] = $result;	
				}

			}
			echo json_encode($result_obj);			
			
		}  else {  // more deposits than visible

				$limit = $visible_count + 1;
				$query = 'SELECT transaction_id FROM transaction_log 
							WHERE transaction_type="Deposit" AND transaction_id<=' . $deposit[0] . 
							' ORDER BY transaction_id DESC LIMIT ' . $limit . ';';	
						   
				$sql = mysql_query($query, $YBDB) or die(mysql_error());
				
				while ( $result = mysql_fetch_assoc($sql) ) {
					$transaction_id[] = $result['transaction_id'];					
				} 
				
				foreach ( $transaction_id as $key => $value ) { 
						
					if ($key <= $c && $transaction_id[$key + 1]) {
						$query = 'SELECT  SUM(IF(payment_type="check", amount, 0)) AS "check",  
					    			SUM(IF(payment_type="credit", amount, 0)) AS "credit",  
					    			SUM(IF(payment_type="cash", amount, 0)) AS "cash"  
					    			FROM transaction_log WHERE paid=1 AND transaction_id <' . $transaction_id[$key] . ' AND transaction_id >' 
					    			. $transaction_id[$key + 1] . ';';
						$sql = mysql_query($query, $YBDB) or die(mysql_error());
						$result = mysql_fetch_assoc($sql);
						$result_obj[$transaction_id[$key]] = $result; 	
					} elseif ($key <= $c && !$transaction_id[$key + 1] ) {
						$query = 'SELECT  SUM(IF(payment_type="check", amount, 0)) AS "check",  
						    		SUM(IF(payment_type="credit", amount, 0)) AS "credit",  
						    		SUM(IF(payment_type="cash", amount, 0)) AS "cash"  
						    		FROM transaction_log WHERE paid=1 AND transaction_id <' . $transaction_id[$key] .  ';';
						$sql = mysql_query($query, $YBDB) or die(mysql_error());
						$result = mysql_fetch_assoc($sql);
						$result_obj[$transaction_id[$key]] = $result; 					
					}									
				
				} // foreach
				echo json_encode($result_obj);								
		} // end  else for invisibles

	} // End Deposit Calculator
	
	function membership_benefits($contact_id) {
		
	global $database_YBDB, $YBDB;
	mysql_select_db($database_YBDB, $YBDB);
	
	$query = "SELECT contacts.contact_id,  CONCAT(last_name, ', ', first_name, ' ',middle_initial) AS full_name,
				CONCAT(first_name, ' ', last_name) AS normal_full_name, contacts.email AS email, contacts.phone AS phone,  
				transaction_log.date AS membership_start, SUBSTRING_INDEX(DATE_ADD(date, INTERVAL 365 DAY), ' ', 1) AS expiration_date 
				FROM transaction_log  LEFT JOIN contacts ON transaction_log.sold_to = contacts.contact_id  
				WHERE SUBSTRING_INDEX(date, ' ', 1) <= DATE_ADD(date, INTERVAL 365 DAY)  
				AND (transaction_type='Memberships' AND paid=1) AND contact_id=" .
				$contact_id . 
				" ORDER by membership_start DESC;";		
		
	 $sql = mysql_query($query, $YBDB) or die(mysql_error());
	 $result = mysql_fetch_assoc($sql);
	 return $result;
	
	} // end membership_benefits

?>