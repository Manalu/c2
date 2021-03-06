	`<?php defined('BASEPATH') OR exit('No direct script access allowed');


require APPPATH.'/libraries/REST_Controller.php';

class UtibillReports extends REST_Controller {
	public $_database;
	public $server_host;
	public $server_user;
	public $server_pwd;
	function __construct() {
		parent::__construct();
		$institute = new Institute();
		$institute->where('id', $this->input->get_request_header('Institute'))->get();
		if($institute->exists()) {
			$conn = $institute->connection->get();
			$this->server_host = $conn->server_name;
			$this->server_user = $conn->username;
			$this->server_pwd = $conn->password;
			$this->_database = $conn->inst_database;
			date_default_timezone_set("$conn->time_zone");
		}
		// $this->_database = "db_banhji";

	}

	//****************HEANG******************
	//Water Sale
	function sale_summary_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->include_related("winvoice_line", array("quantity", "type"));
		$obj->where_related("winvoice_line", "type", "usage");
		$obj->where("type", "Utility_Invoice");
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {								
				$amount = floatval($value->amount)/floatval($value->rate);

				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["invoice"] 		+= 1;
					$objList[$value->contact_id]["amount"] 			+= $amount;
				}else{
					$objList[$value->contact_id]["id"] 				= $value->contact_id;
					$objList[$value->contact_id]["name"] 			= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["invoice"]			= 1;
					$objList[$value->contact_id]["location"]		= $value->location_name;
					$objList[$value->contact_id]["amount"]			= $amount;
					$objList[$value->contact_id]["usage"]			=  $value->winvoice_line_quantity;
				}
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}
	function sale_detail_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$total = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results

		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("winvoice_line", array("quantity", "type"));
		$obj->where_related("winvoice_line", "type", "usage");
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {								
				$amount = floatval($value->amount)/ floatval($value->rate);
				
				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"date" 				=> $value->issued_date,
						"location" 			=> $value->location_name,
						"number" 			=> $value->number,
						"usage" 			=> $value->winvoice_line_quantity,
						"amount"			=> $amount
					);
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][]	= array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"date" 				=> $value->issued_date,
						"location" 			=> $value->location_name,
						"number" 			=> $value->number,
						"usage" 			=> $value->winvoice_line_quantity,
						"amount"			=> $amount
					);
				}
				$total +=  $amount;
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data['total'] = $total;
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Fine Collect
	function fine_collect_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$total = 0;

		$obj = new Journal_Line(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results

		$obj->include_related("transaction", array("issued_date", "number"));
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related('transaction/location', "name");
		$obj->where("account_id", "110");
		$obj->where("deleted <>", 1);
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {								
				$amount = floatval($value->cr)/ floatval($value->rate);
				
				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 				=> $value->id,
						"type" 				=> $value->description,
						"date" 				=> $value->transaction_issued_date,
						"location" 			=> $value->transaction_location_name,
						"number" 			=> $value->transaction_number,
						"amount"			=> $amount
					);
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][]	= array(
						"id" 				=> $value->id,
						"type" 				=> $value->description,
						"date" 				=> $value->transaction_issued_date,
						"location" 			=> $value->transaction_location_name,
						"number" 			=> $value->transaction_number,
						"amount"			=> $amount
					);
				}
				$total +=  $amount;
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data['total'] = $total;
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//BALANCE
	function balance_summary_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {
				$amount = floatval($value->amount) / floatval($value->rate);

				if($value->status=="2"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where("reference_id", $value->id);
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice"));
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount -= floatval($paid->amount) + floatval($paid->discount);
				}

				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["id"] 				= $value->id;
					$objList[$value->contact_id]["amount"] 			+= $amount;
					$objList[$value->contact_id]["number"] 			+= 1;
				}else{
					$objList[$value->contact_id]["id"] 				= $value->contact_id;
					$objList[$value->contact_id]["name"] 			= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["type"]			= $value->type;
					$objList[$value->contact_id]["issued_date"]		= $value->issued_date;
					$objList[$value->contact_id]["due_date"]		= $value->due_date;
					$objList[$value->contact_id]["location"]		= $value->location;
					$objList[$value->contact_id]["status"]			= $value->status;
					$objList[$value->contact_id]["number"] 			= 1;
					$objList[$value->contact_id]["amount"]			= $amount;
				}
			}
			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}
	function balance_detail_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {
				$amount = floatval($value->amount) / floatval($value->rate);

				if($value->status=="2"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where("reference_id", $value->id);
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice"));
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount -= floatval($paid->amount) + floatval($paid->discount);
				}
				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"number" 			=> $value->number,
						"issued_date" 		=> $value->issued_date,
						"due_date" 			=> $value->due_date,
						"location" 			=> $value->location_name,
						"amount" 			=> $amount
					);
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][]	= array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"number" 			=> $value->number,
						"due_date" 			=> $value->due_date,
						"issued_date" 		=> $value->issued_date,
						"location" 			=> $value->location_name,
						"amount" 			=> $amount
					);
				}
			}
			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Account Receiveble Water
	function Reciveble_invoice_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->get_iterated();
		
		if($obj->exists()){
			foreach ($obj as $value) {
				$amount = floatval($value->amount) / floatval($value->rate);

				if($value->status=="2"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where("reference_id", $value->id);
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice"));
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount -= floatval($paid->amount) + floatval($paid->discount);
				}
				
				$data["results"][] = array(
					"id" 				=> $value->id,
					"name" 				=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
					"type" 				=> $value->type,
					"number" 			=> $value->number,
					"issued_date" 		=> $value->issued_date,
					"due_date" 			=> $value->due_date,
					"location" 			=> $value->location_name,
					"status"			=> $value->status,
					"rate" 				=> $value->rate,
					"amount" 			=> $amount
				);
			}

			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Deposit
	function deposit_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Deposit");
		$obj->where("is_recurring <>", 1);
		$obj->where("status <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {								
				$amount = floatval($value->amount) / floatval($value->rate);

				$reference = [];
				if($value->reference_id>0){
					$ref = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$ref->where("is_recurring <>", 1);
					$ref->where("deleted <>", 1);
					$ref->where("id", $value->reference_id);
					$reference = $ref->get_raw()->result();
				}
				
				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"number" 			=> $value->number,
						"issued_date" 		=> $value->issued_date,
						"rate" 				=> $value->rate,
						"location" 			=> $value->location_name,
						"amount" 			=> $amount,
						"reference" 		=> $reference
					);
				}else{
					//Balance Forward
					$bf = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);					
					$bf->where("issued_date <", $value->issued_date);
					$bf->where("contact_id", $value->contact_id);
					$bf->where("type", "Water_Deposit");
					$bf->where("is_recurring <>", 1);
					$bf->where("deleted <>", 1);
					$bf->get_iterated();

					$balance_forward = 0;
					if($bf->exists()){
						foreach ($bf as $val) {
							$balance_forward += floatval($val->amount) / floatval($val->rate);
						}
					}

					$objList[$value->contact_id]["id"] 				= $value->contact_id;
					$objList[$value->contact_id]["name"] 			= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["balance_forward"] = $balance_forward;
					$objList[$value->contact_id]["line"][]			= array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"number" 			=> $value->number,
						"issued_date" 		=> $value->issued_date,
						"rate" 				=> $value->rate,
						"location" 			=> $value->location_name,
						"amount" 			=> $amount,
						"reference" 		=> $reference
					);
				}
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}
	
	//Customer Aging
	function aging_summary_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			$today = new DateTime();
			foreach ($obj as $value) {
				$amount = (floatval($value->amount) - floatval($value->deposit)) / floatval($value->rate);
				
				if($value->status=="2"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where("reference_id", $value->id);
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice"));
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount -= floatval($paid->amount) + floatval($paid->discount);
				}

				$current = 0;
				$in30 = 0;
				$in60 = 0;
				$in90 = 0;
				$over90 = 0;

				$dueDate = new DateTime($value->due_date);
				$days = $dueDate->diff($today)->format("%a");
				if($dueDate < $today){
					if(intval($days)>90){
						$over90 = $amount;
					}else if(intval($days)>60){
						$in90 = $amount;
					}else if(intval($days)>30){
						$in60 = $amount;
					}else{
						$in30 = $amount;
					}
				}else{
					$current = $amount;
				}

				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["current"] += $current;
					$objList[$value->contact_id]["in30"] 	+= $in30;
					$objList[$value->contact_id]["in60"] 	+= $in60;
					$objList[$value->contact_id]["in90"] 	+= $in90;
					$objList[$value->contact_id]["over90"] 	+= $over90;
					$objList[$value->contact_id]["total"] 	+= $amount;
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["current"] = $current;
					$objList[$value->contact_id]["in30"] 	= $in30;
					$objList[$value->contact_id]["in60"] 	= $in60;
					$objList[$value->contact_id]["in90"] 	= $in90;
					$objList[$value->contact_id]["over90"] 	= $over90;
					$objList[$value->contact_id]["total"] 	= $amount;
				}
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}
	function aging_detail_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {
				$amount = (floatval($value->amount) - floatval($value->deposit)) / floatval($value->rate);
				
				if($value->status=="2"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where("reference_id", $value->id);
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice"));
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount -= floatval($paid->amount) + floatval($paid->discount);
				}

				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"number" 			=> $value->number,
						"issued_date" 		=> $value->issued_date,
						"due_date" 			=> $value->due_date,
						"memo" 				=> $value->memo,
						"status"			=> $value->status,
						"location" 			=> $value->location_name,
						"rate" 				=> $value->rate,
						"amount" 			=> $amount
					);
				}else{
					$objList[$value->contact_id]["id"] 				= $value->contact_id;
					$objList[$value->contact_id]["name"] 			= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][] 			= array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"number" 			=> $value->number,
						"issued_date" 		=> $value->issued_date,
						"due_date" 			=> $value->due_date,
						"memo" 				=> $value->memo,
						"status"			=> $value->status,
						"location" 			=> $value->location_name,
						"rate" 				=> $value->rate,
						"amount" 			=> $amount
					);			
				}
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Cash Receipt Detail 
	function cash_receipt_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$cashReceipt = 0;
		$total = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->where("type", "Cash_Receipt");
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {
				//Reference
				$ref = $value->reference->select("type, number, issued_date, amount, deposit, rate")->get();				
				$refAmount = (floatval($ref->amount) - floatval($ref->deposit)) / floatval($ref->rate);
				$cashReceipt +=1;
				$amount = (floatval($value->amount) - floatval($value->deposit)) / floatval($value->rate);

				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 					=> $value->id,
						"type" 					=> $value->type,
						"number" 				=> $value->number,
						"issued_date" 			=> $value->issued_date,
						"amount" 				=> $amount,
						"reference_id" 			=> $value->reference_id,
						"reference_type" 		=> $ref->type,
						"reference_number" 		=> $ref->number,
						"reference_issued_date" => $ref->issued_date,
						"reference_amount" 		=> $refAmount
					);
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][] 	= array(
						"id" 					=> $value->id,
						"type" 					=> $value->type,
						"number" 				=> $value->number,
						"issued_date" 			=> $value->issued_date,
						"amount" 				=> $amount,
						"reference_id" 			=> $value->reference_id,
						"reference_type" 		=> $ref->type,
						"reference_number" 		=> $ref->number,
						"reference_issued_date" => $ref->issued_date,
						"reference_amount" 		=> $refAmount
					);			
				}
				$total += $amount;
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data['total'] = $total;
			$data['cashReceipt'] = $cashReceipt;
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Cash Receipt Source 
	function cash_receipt_source_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$total = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("payment_method", "name");
		$obj->include_related("location", "name");
		$obj->where("type", "Cash_Receipt");
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {
				//Reference
				$ref = $value->reference->select("type, number, issued_date, amount, deposit, rate")->get();				
				$refAmount = (floatval($ref->amount) - floatval($ref->deposit)) / floatval($ref->rate);

				$amount = (floatval($value->amount) - floatval($value->deposit)) / floatval($value->rate);

				if(isset($objList[$value->payment_method_name])){
					$objList[$value->payment_method_name]["line"][] = array(
						"id" 					=> $value->id,
						"name" 					=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
						"number" 				=> $value->number,
						"date" 					=> $value->issued_date,
						"location" 				=> $value->location_name,
						"rate" 					=> $value->rate,
						"amount" 				=> $amount
					);
				}else{
					$objList[$value->payment_method_name]["id"] 		= $value->payment_method_name;
					$objList[$value->payment_method_name]["payment"] 	= $value->payment_method_name;
					$objList[$value->payment_method_name]["line"][] 	= array(
						"id" 					=> $value->id,
						"type" 					=> $value->type,
						"name" 					=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
						"number" 				=> $value->number,
						"date" 					=> $value->issued_date,
						"location" 				=> $value->location_name,
						"rate" 					=> $value->rate,
						"amount" 				=> $amount
					);
				}
				$total += $amount;
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data['total'] = $total;
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Connnection Service Revenue
	function connect_service_revenue_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$total = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results

		$obj->include_related("contact", array("abbr", "number", "name"));
		$obj->include_related("location", "name");
		$obj->include_related('location/branch', "name");
		$obj->where("type", "Meter_Activation");
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->order_by("issued_date", "asc");
		$obj->get_iterated();
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {								
				$amount = floatval($value->amount)/ floatval($value->rate);
				
				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"date" 				=> $value->issued_date,
						"location" 			=> $value->location_name,
						"number" 			=> $value->number,
						"branch" 			=> $value->location_branch_name,
						"amount"			=> $amount
					);
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][]	= array(
						"id" 				=> $value->id,
						"type" 				=> $value->type,
						"date" 				=> $value->issued_date,
						"location" 			=> $value->location_name,
						"number" 			=> $value->number,
						"branch" 			=> $value->location_branch_name,
						"amount"			=> $amount
					);
				}
				$total +=  $amount;
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data['total'] = $total;
			$data["count"] = count($data["results"]);
		}

		//Response Data
		$this->response($data, 200);
	}

	//Customer List
	function customer_list_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "address", "phone", "name"));
		$obj->include_related("property", array("abbr", "name"));
		$obj->include_related("location", "name");
		$obj->include_related("branch", "name");
		$obj->where("status", 1);		
		$obj->get_paged_iterated($page, $limit);
		
		if($obj->exists()){
			$objList = [];
			foreach ($obj as $value) {								
				
				if(isset($objList[$value->contact_id])){
					$objList[$value->contact_id]["line"][] = array(
						"id"		=> $value->id,
						"meter"		=> $value->number,
						"location"  => $value->location_name,
						"branch"	=> $value->branch_name,
						"property"	=> $value->property_name,
					);
				}else{
					$objList[$value->contact_id]["id"] 		= $value->contact_id;
					$objList[$value->contact_id]["name"] 	= $value->contact_abbr.$value->contact_number." ".$value->contact_name;
					$objList[$value->contact_id]["line"][]	= array(
						"id"		=> $value->id,
						"meter"		=> $value->number,
						"location"  => $value->location_name,
						"branch"	=> $value->branch_name,
						"property"	=> $value->property_name,
					);
				}
			}

			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = $obj->paged->total_rows;
			$data["currentPage"] = $obj->paged->current_page;
		}

		//Response Data
		$this->response($data, 200);
	}

	//Disconnection List
	function disconnection_list_get() {
		$filters 	= $this->get("filter");		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;								
		$sort 	 	= $this->get("sort");		
		$is_pattern = 0;

		$obj = new Meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

		//Sort
		if(!empty($sort) && isset($sort)){				
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter		
		if(!empty($filters) && isset($filters['filters'])){
	    	foreach ($filters['filters'] as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}
		$obj->include_related("contact", array("abbr", "number", "name", "email", "address", "phone", "id"));
		$obj->include_related("location", "name");
		$obj->include_related("branch", "name");
		$obj->where("status", 0);
		$obj->get_paged_iterated($page, $limit);
		if($obj->exists()) {
			$data = array();
			foreach($obj as $value) {
				//$utility = $row->contact->include_related('utility', array('abbr', 'code'))->get();
				$data[] = array(
					"id" 		=> $value->id,
					"name"		=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
					"license" 	=> $value->branch_name,
					"number" 	=> $value->contact_abbr ."-". $value->contact_number,
					"phone"     => $value->contact_phone,
					"address"	=> $value->contact_address
				);
			}
			$this->response(array('results' => $data, 'count' => $obj->paged->total_rows), 200);
		} else {
			$this->response(array('results'=> array()));
		}
	}

	//inactive List
	function inactive_list_get() {
		$filters 	= $this->get("filter");		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;								
		$sort 	 	= $this->get("sort");		
		$is_pattern = 0;

		$obj = new Meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

		//Sort
		if(!empty($sort) && isset($sort)){				
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter		
		if(!empty($filters) && isset($filters['filters'])){
	    	foreach ($filters['filters'] as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}
		$obj->include_related("contact", array("abbr", "number", "name", "email", "address", "phone", "id"));
		$obj->include_related("location", "name");
		$obj->include_related("branch", "name");
		$obj->where("status", 2);
		$obj->get_paged_iterated($page, $limit);
		if($obj->exists()) {
			$data = array();
			foreach($obj as $value) {
				//$utility = $row->contact->include_related('utility', array('abbr', 'code'))->get();
				$data[] = array(
					"id" 		=> $value->id,
					"name"		=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
					"license" 	=> $value->branch_name,
					"number" 	=> $value->contact_abbr ."-". $value->contact_number,
					"phone"     => $value->contact_phone,
					"address"	=> $value->contact_address
				);
			}
			$this->response(array('results' => $data, 'count' => $obj->paged->total_rows), 200);
		} else {
			$this->response(array('results'=> array()));
		}
	}

	//To be Disconnection List
	function to_be_disconnection_list_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				if(isset($value['operator'])){
					$obj->{$value['operator']}($value["field"], $value["dir"]);
				}else{
					$obj->order_by($value["field"], $value["dir"]);
				}
			}
		}
		
		//Filter		
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])){
	    			$obj->{$value['operator']}($value['field'], $value['value']);	    		
	    		} else {
	    			$obj->where($value['field'], $value['value']);
	    		}
			}
		}

		//Results
		$obj->include_related("contact", array("abbr", "number", "name", "address"));
		$obj->include_related("location", "name");
		$obj->where("type", "Utility_Invoice");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->get_paged_iterated($page, $limit);
		
		if($obj->exists()){
			foreach ($obj as $value) {
				$amount = floatval($value->amount) / floatval($value->rate);

				if($value->status=="2"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where("reference_id", $value->id);
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice"));
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount -= floatval($paid->amount) + floatval($paid->discount);
				}
				
				$data["results"][] = array(
					"id" 				=> $value->id,
					"name" 				=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
					"type" 				=> $value->type,
					"number" 			=> $value->number,
					"issued_date" 		=> $value->issued_date,
					"due_date" 			=> $value->due_date,
					"location" 			=> $value->location_name,
					"status"			=> $value->status,
					"rate" 				=> $value->rate,
					"address" 			=> $value->contact_address
				);
			}

			$data["count"] = $obj->paged->total_rows;
			$data["currentPage"] = $obj->paged->current_page;
		}

		//Response Data
		$this->response($data, 200);
	}

	//New Customer List
	function newProperty_list_get() {
		$filters 	= $this->get("filter");		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;								
		$sort 	 	= $this->get("sort");		
		$is_pattern = 0;
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

		//Sort
		if(!empty($sort) && isset($sort)){				
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter		
		if(!empty($filters) && isset($filters['filters'])){
	    	foreach ($filters['filters'] as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}
		$obj->include_related("property", array("id", "abbr", "name", "address"));
		$obj->get_paged_iterated($page, $limit);
		if($obj->exists()) {
			$data = array();
			$objList = [];
			foreach($obj as $value) {
				//$utility = $row->contact->include_related('utility', array('abbr', 'code'))->get();

		
					if(isset($objList[$value->contact_id])){
						$objList[$value->contact_id]["invoice"] 		+= 1;
					}else{
						$objList[$value->contact_id]["invoice"]			= 1;
						$objList[$value->contact_id]["id"] 				= $value->property_id;
						$objList[$value->contact_id]["name"] 			= $value->property_name;
						$objList[$value->contact_id]["abbr"]			= $value->property_abbr;
						$objList[$value->contact_id]["address"]			= $value->property_address;
				}
			}
			foreach ($objList as $value) {
				$data["results"][] = $value;
			}
			$data["count"] = count($data["results"]);
		}
		$this->response($data, 200);
	}

	//Customer  No Connecting List
	function noConnection_list_get() {
		$filters 	= $this->get("filter");		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;								
		$sort 	 	= $this->get("sort");		
		$is_pattern = 0;
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Contact(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

		//Sort
		if(!empty($sort) && isset($sort)){				
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter		
		if(!empty($filters) && isset($filters['filters'])){
	    	foreach ($filters['filters'] as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}
		$obj->include_related("meter", "id");
		$obj->include_related("location", "name");
		$obj->include_related("branch", "name");
		$obj->where("use_water", 1);
		$obj->get_paged_iterated($page, $limit);
		if($obj->exists()) {
			$data["count"] = count($data["results"]);			
		} else {		
			$data = array();
			foreach($obj as $value) {
				//$utility = $row->contact->include_related('utility', array('abbr', 'code'))->get();
				$data[] = array(
					"id" 		=> $value->id,
					"name"		=> $value->contact_abbr.$value->contact_number." ".$value->contact_name,
					"branch" 	=> $value->branch_name,
					"address"	=> $value->contact_address,
					"phone"		=> $value->contact_phone,
					"email" 	=> $value->contact_email,
					"location" 	=> $value->location_name,
				);
			}
			$this->response(array('results' => $data, 'count' => $obj->paged->total_rows), 200);
		}
	}

	//usage
	function miniusage_get() {
		$filters 	= $this->get("filter");		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;								
		$sort 	 	= $this->get("sort");		
		$is_pattern = 0;
		$data["results"] = [];
		$data["count"] = 0;
		$total = 0;

		$obj = new Meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

		//Sort
		if(!empty($sort) && isset($sort)){				
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter		
		if(!empty($filters) && isset($filters['filters'])){
	    	foreach ($filters['filters'] as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}
		$obj->include_related("contact", array("abbr", "number", "address", "phone", "name"));
		$obj->include_related('branch', array('name'));
		$obj->include_related('record', array('from_date', 'to_date', 'usage'));
		$obj->include_related('location', array('name'));
		$obj->get_paged_iterated($page, $limit);
		if($obj->exists()) {
			$data = array();
			foreach($obj as $row) {
				$usage = $row->record_usage;
				$data[] = array(
					"id" => $row->id,
					"meter_number" => $row->contact_name." ".$row->number,
					"from_date"=>$row->record_from_date,
					"to_date" =>$row->record_to_date,
					"license" => $row->branch_name,
					"address"=> $row->location_name,
					"usage" => $row->record_usage,
				);
				$total += $usage;
				
			}
			$this->response(array('results' => $data, 'count' => $obj->paged->total_rows, 'amount' => $total), 200);
		} else {
			$this->response($data, 200);;
		}
		$data["count"] = $obj->paged->total_rows;
		$data["currentPage"] = $obj->paged->current_page;
	}
}
/* End of file winvoices.php */
/* Location: ./application/controllers/api/categories.php */
