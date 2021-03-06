<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); //disallow direct access to this file

require APPPATH.'/libraries/REST_Controller.php';

class Transactions extends REST_Controller {
	public $_database;
	public $server_host;
	public $server_user;
	public $server_pwd;
	//CONSTRUCTOR
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
	}


	//GET
	function index_get() {
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$is_recurring = 0;

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
		if(!empty($filter["filters"]) && isset($filter["filters"])){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value['operator'])) {
					if($value['operator']=="startswith"){
	    				$obj->like($value['field'], $value['value'], 'after');
	    			}else if($value['operator']=="contains"){
	    				$obj->like($value['field'], $value['value'], 'both');
	    			}else{
						$obj->{$value['operator']}($value['field'], $value['value']);
	    			}
				} else {
					if($value["field"]=="is_recurring"){
	    				$is_recurring = $value["value"];
	    			}else{
	    				$obj->where($value["field"], $value["value"]);
	    			}
				}
			}
		}

		$obj->include_related("contact", array("abbr","number","name","payment_term_id","payment_method_id","credit_limit","locale","bill_to","ship_to","deposit_account_id","trade_discount_id","settlement_discount_id","account_id","ra_id"));
		$obj->where("is_recurring", $is_recurring);
		$obj->where("deleted <>", 1);

		//Results
		if($page && $limit){
			$obj->get_paged_iterated($page, $limit);
			$data["count"] = $obj->paged->total_rows;
		}else{
			$obj->get_iterated();
			$data["count"] = $obj->result_count();
		}

		if($obj->exists()){
			foreach ($obj as $value) {
				//Sum amount paid
				$amount_paid = 0;
				if($value->type=="Commercial_Invoice" || $value->type=="Vat_Invoice" || $value->type=="Invoice" || $value->type=="Credit_Purchase" || $value->type=="Utility_Invoice"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where_in("type", array("Cash_Receipt", "Offset_Invoice", "Cash_Payment", "Offset_Bill"));					
					$paid->where("reference_id", $value->id);					
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount_paid = floatval($paid->amount) + floatval($paid->discount);
				}else if($value->type=="Cash_Advance"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("received");
					$paid->where("type", "Advance_Settlement");
					$paid->where("reference_id", $value->id);
					$paid->where("is_recurring <>",1);
					$paid->where("deleted <>",1);
					$paid->get();
					$amount_paid = floatval($paid->amount) + floatval($paid->received);
				}

				//Meter By Choeun
				$meter = "";
				$meterNum = "";
				if($value->meter_id != 0){
					$meter = $value->meter->get();
					$meterNum = $meter->get()->number;
				}

				//Contact
				$contact = array(
					"id" 						=> $value->contact_id,
					"abbr"						=> $value->contact_abbr ? $value->contact_abbr : "",
					"number"					=> $value->contact_number ? $value->contact_number : "",
					"name"						=> $value->contact_name ? $value->contact_name : "",
					"payment_term_id"			=> $value->contact_payment_term_id ? $value->contact_payment_term_id : 0,
					"payment_method_id"			=> $value->contact_payment_method_id ? $value->contact_payment_method_id : 0,
					"credit_limit"				=> $value->contact_credit_limit ? $value->contact_credit_limit : 0,
					"locale"					=> $value->contact_locale ? $value->contact_locale : "",
					"bill_to"					=> $value->contact_bill_to ? $value->contact_bill_to : "",
					"ship_to"					=> $value->contact_ship_to ? $value->contact_ship_to : "",
					"deposit_account_id"		=> $value->contact_deposit_account_id ? $value->contact_deposit_account_id : 0,
					"trade_discount_id"			=> $value->contact_trade_discount_id ? $value->contact_trade_discount_id : 0,
					"settlement_discount_id"	=> $value->contact_settlement_discount_id ? $value->contact_settlement_discount_id : 0,
					"account_id"				=> $value->contact_account_id ? $value->contact_account_id : 0,
					"ra_id"						=> $value->contact_ra_id ? $value->contact_ra_id : 0
				);

				//Employee
				$employee = [];
				if($value->employee_id>0){
					$employies = new Contact(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$employies->select("abbr, number, name, salary_account_id");
					$employies->get_by_id($value->employee_id);

					$employee = array(
						"id" 				=> $value->employee_id,
						"abbr" 				=> $employies->abbr,
						"number" 			=> $employies->number,
						"name" 				=> $employies->name,
						"salary_account_id"	=> $employies->salary_account_id
					);
				}

				$data["results"][] = array(
					"id" 						=> $value->id,
					"company_id" 				=> $value->company_id,
					"location_id" 				=> $value->location_id,
					"contact_id" 				=> intval($value->contact_id),
					"payment_term_id" 			=> $value->payment_term_id,
					"payment_method_id" 		=> intval($value->payment_method_id),
					"transaction_template_id" 	=> $value->transaction_template_id,
					"reference_id" 				=> intval($value->reference_id),
					"recurring_id" 				=> $value->recurring_id,
					"return_id" 				=> $value->return_id,
					"job_id" 					=> $value->job_id,
					"account_id" 				=> intval($value->account_id),
					"item_id" 					=> $value->item_id,
					"tax_item_id" 				=> $value->tax_item_id,
					"wht_account_id"			=> $value->wht_account_id,
					"user_id" 					=> $value->user_id,
					"employee_id" 				=> $value->employee_id,
				   	"number" 					=> $value->number,
				   	"type" 						=> $value->type,
				   	"journal_type" 				=> $value->journal_type,
				   	"sub_total"					=> floatval($value->sub_total),
				   	"discount" 					=> floatval($value->discount),
				   	"tax" 						=> floatval($value->tax),
				   	"amount" 					=> floatval($value->amount),
				   	"fine" 						=> floatval($value->fine),
				   	"deposit"					=> floatval($value->deposit),
				   	"remaining" 				=> floatval($value->remaining),
				   	"received" 					=> floatval($value->received),
				   	"change" 					=> floatval($value->change),
				   	"credit_allowed"			=> floatval($value->credit_allowed),
				   	"additional_cost" 			=> floatval($value->additional_cost),
				   	"additional_apply" 			=> $value->additional_apply,
				   	"rate" 						=> floatval($value->rate),
				   	"locale" 					=> $value->locale,
				   	"month_of"					=> $value->month_of,
				   	"issued_date"				=> $value->issued_date,
				   	"bill_date"					=> $value->bill_date,
				   	"payment_date" 				=> $value->payment_date,
				   	"due_date" 					=> $value->due_date,
				   	"deposit_date" 				=> $value->deposit_date,
				   	"check_no" 					=> $value->check_no,
				   	"reference_no" 				=> $value->reference_no,
				   	"references" 				=> $value->references!="" ? array_map('intval', explode(",", $value->references)) : [],
				   	"segments" 					=> $value->segments!="" ? array_map('intval', explode(",", $value->segments)) : [],
				   	"bill_to" 					=> $value->bill_to,
				   	"ship_to" 					=> $value->ship_to,
				   	"memo" 						=> $value->memo,
				   	"memo2" 					=> $value->memo2,
				   	"note" 						=> $value->note,
				   	"recurring_name" 			=> $value->recurring_name,
				   	"start_date"				=> $value->start_date,
				   	"frequency"					=> $value->frequency,
					"month_option"				=> $value->month_option,
					"interval" 					=> $value->interval,
					"day" 						=> $value->day,
					"week" 						=> $value->week,
					"month" 					=> $value->month,
				   	"status" 					=> intval($value->status),
				   	"progress" 					=> $value->progress,
				   	"is_recurring" 				=> intval($value->is_recurring),
				   	"is_journal" 				=> $value->is_journal,
				   	"print_count" 				=> $value->print_count,
				   	"printed_by" 				=> $value->printed_by,
				   	"deleted" 					=> $value->deleted,
				   	"meter"						=> $meterNum,
				   	"meter_id"					=> $value->meter_id,
				   	"amount_paid"				=> $amount_paid,

				   	"contact" 					=> $contact,
				   	"employee" 					=> $employee,
				   	"reference" 				=> []
				);
			}
		}

		//Response Data
		$this->response($data, 200);
	}

	//POST
	function index_post() {
		$models = json_decode($this->post('models'));
		$data["results"] = [];
		$data["count"] = 0;
		
		$number = "";
		foreach ($models as $value) {
			//Generate Number
			if(isset($value->number)){
				$number = $value->number;

				if($number==""){
					$number = $this->_generate_number($value->type, $value->issued_date);
				}
			}else{
				$number = $this->_generate_number($value->type, $value->issued_date);
			}
			
			if(isset($value->is_recurring)){
				if($value->is_recurring==1){
					$number = "";
				}
			}

			$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			isset($value->company_id) 				? $obj->company_id 					= $value->company_id : "";
			isset($value->location_id) 				? $obj->location_id 				= $value->location_id : "";
			isset($value->contact_id) 				? $obj->contact_id 					= $value->contact_id : "";
			isset($value->payment_term_id) 			? $obj->payment_term_id 			= $value->payment_term_id : 5;
			isset($value->payment_method_id) 		? $obj->payment_method_id 			= $value->payment_method_id : "";
			isset($value->transaction_template_id) 	? $obj->transaction_template_id 	= $value->transaction_template_id : "";
			isset($value->reference_id) 			? $obj->reference_id 				= $value->reference_id : "";
			isset($value->recurring_id) 			? $obj->recurring_id 				= $value->recurring_id : "";
			isset($value->return_id) 				? $obj->return_id 					= $value->return_id : "";
			isset($value->job_id) 					? $obj->job_id 						= $value->job_id : "";
			isset($value->account_id) 				? $obj->account_id 					= $value->account_id : "";
			isset($value->item_id) 					? $obj->item_id 					= $value->item_id : "";
			isset($value->tax_item_id) 				? $obj->tax_item_id 				= $value->tax_item_id : "";
			isset($value->wht_account_id) 			? $obj->wht_account_id 				= $value->wht_account_id : "";
			isset($value->user_id) 					? $obj->user_id 					= $value->user_id : "";
			isset($value->employee_id) 				? $obj->employee_id 				= $value->employee_id : "";
			$obj->number = $number;
		   	isset($value->type) 					? $obj->type 						= $value->type : "";
		   	isset($value->journal_type) 			? $obj->journal_type 				= $value->journal_type : "";
		   	isset($value->sub_total) 				? $obj->sub_total 					= $value->sub_total : "";
		   	isset($value->discount) 				? $obj->discount 					= $value->discount : "";
		   	isset($value->tax) 						? $obj->tax 						= $value->tax : "";
		   	isset($value->amount) 					? $obj->amount 						= $value->amount : "";
		   	isset($value->fine) 					? $obj->fine 						= $value->fine : "";
		   	isset($value->deposit) 					? $obj->deposit 					= $value->deposit : "";
		   	isset($value->remaining) 				? $obj->remaining 					= $value->remaining : "";
		   	isset($value->received) 				? $obj->received 					= $value->received : "";
		   	isset($value->change) 					? $obj->change 						= $value->change : "";
		   	isset($value->credit_allowed) 			? $obj->credit_allowed 				= $value->credit_allowed : "";
		   	isset($value->additional_cost) 			? $obj->additional_cost 			= $value->additional_cost : "";
		   	isset($value->additional_apply) 		? $obj->additional_apply 			= $value->additional_apply : "";
		   	isset($value->rate) 					? $obj->rate 						= $value->rate : "";
		   	isset($value->locale) 					? $obj->locale 						= $value->locale : "";
		   	isset($value->month_of) 				? $obj->month_of 					= $value->month_of : "";
		   	isset($value->issued_date) 				? $obj->issued_date 				= $value->issued_date : "";
		   	isset($value->bill_date) 				? $obj->bill_date 					= $value->bill_date : "";
		   	isset($value->payment_date) 			? $obj->payment_date 				= $value->payment_date : "";
		   	isset($value->due_date) 				? $obj->due_date 					= $value->due_date : "";
		   	isset($value->deposit_date) 			? $obj->deposit_date 				= $value->deposit_date : "";
		   	isset($value->check_no) 				? $obj->check_no 					= $value->check_no : "";
		   	isset($value->reference_no) 			? $obj->reference_no 				= $value->reference_no : "";
		   	isset($value->references) 				? $obj->references 					= implode(",", $value->references) : "";
		   	isset($value->segments) 				? $obj->segments 					= implode(",", $value->segments) : "";
		   	isset($value->bill_to) 					? $obj->bill_to 					= $value->bill_to : "";
		   	isset($value->ship_to) 					? $obj->ship_to 					= $value->ship_to : "";
		   	isset($value->memo) 					? $obj->memo 						= $value->memo : "";
		   	isset($value->memo2) 					? $obj->memo2 						= $value->memo2 : "";
		   	isset($value->note) 					? $obj->note 						= $value->note : "";
		   	isset($value->recurring_name) 			? $obj->recurring_name 				= $value->recurring_name : "";
		   	isset($value->start_date) 				? $obj->start_date 					= $value->start_date : "";
		   	isset($value->frequency) 				? $obj->frequency 					= $value->frequency : "";
		   	isset($value->month_option) 			? $obj->month_option 				= $value->month_option : "";
		   	isset($value->interval) 				? $obj->interval 					= $value->interval : "";
		   	isset($value->day) 						? $obj->day 						= $value->day : "";
		   	isset($value->week) 					? $obj->week 						= $value->week : "";
		   	isset($value->month) 					? $obj->month 						= $value->month : "";
		   	isset($value->status) 					? $obj->status 						= $value->status : "";
		   	isset($value->progress) 				? $obj->progress 					= $value->progress : "";
		   	isset($value->is_recurring) 			? $obj->is_recurring 				= $value->is_recurring : "";
		   	isset($value->is_journal) 				? $obj->is_journal 					= $value->is_journal : "";
		   	isset($value->print_count) 				? $obj->print_count 				= $value->print_count : "";
		   	isset($value->printed_by) 				? $obj->printed_by 					= $value->printed_by : "";
		   	isset($value->deleted) 					? $obj->deleted 					= $value->deleted : "";
		   	isset($value->meter_id) 				? $obj->meter_id 					= $value->meter_id : "";
		   	
		   	$relatedsegmentitem = new Segmentitem(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			if(isset($value->segments)){
				if(count($value->segments)>0){
					$relatedsegmentitem->where_in("id", $value->segments)->get();
				}
			}

			$contact = [];
			if(isset($value->contact)){
				$contact = $value->contact;
			}

	   		if($obj->save($relatedsegmentitem->all)){
			   	$data["results"][] = array(
			   		"id" 						=> $obj->id,
					"company_id" 				=> $obj->company_id,
					"location_id" 				=> $obj->location_id,
					"contact_id" 				=> $obj->contact_id,
					"payment_term_id" 			=> $obj->payment_term_id,
					"payment_method_id" 		=> $obj->payment_method_id,
					"transaction_template_id" 	=> $obj->transaction_template_id,
					"reference_id" 				=> $obj->reference_id,
					"recuring_id" 				=> $obj->recuring_id,
					"return_id" 				=> $obj->return_id,
					"job_id" 					=> $obj->job_id,
					"account_id" 				=> $obj->account_id,
					"item_id" 					=> $obj->item_id,
					"tax_item_id" 				=> $obj->tax_item_id,
					"wht_account_id"			=> $obj->wht_account_id,
					"user_id" 					=> $obj->user_id,
					"employee_id" 				=> $obj->employee_id,
					"number" 					=> $obj->number,
				   	"type" 						=> $obj->type,
				   	"journal_type" 				=> $obj->journal_type,
				   	"sub_total"					=> floatval($obj->sub_total),
				   	"discount" 					=> floatval($obj->discount),
				   	"tax" 						=> floatval($obj->tax),
				   	"amount" 					=> floatval($obj->amount),
				   	"fine" 						=> floatval($obj->fine),
				   	"deposit"					=> floatval($obj->deposit),
				   	"remaining" 				=> floatval($obj->remaining),
				   	"received" 					=> floatval($obj->received),
				   	"change" 					=> floatval($obj->change),
				   	"credit_allowed"			=> floatval($obj->credit_allowed),
				   	"additional_cost" 			=> floatval($obj->additional_cost),
				   	"additional_apply" 			=> $obj->additional_apply,
				   	"rate" 						=> floatval($obj->rate),
				   	"locale" 					=> $obj->locale,
				   	"month_of"					=> $obj->month_of,
				   	"issued_date"				=> $obj->issued_date,
				   	"bill_date"					=> $obj->bill_date,
				   	"payment_date" 				=> $obj->payment_date,
				   	"due_date" 					=> $obj->due_date,
				   	"deposit_date" 				=> $obj->deposit_date,
				   	"check_no" 					=> $obj->check_no,
				   	"reference_no" 				=> $obj->reference_no,
				   	"references" 				=> $obj->references!="" ? array_map('intval', explode(",", $obj->references)) : [],
				   	"segments" 					=> $obj->segments!="" ? array_map('intval', explode(",", $obj->segments)) : [],
				   	"bill_to" 					=> $obj->bill_to,
				   	"ship_to" 					=> $obj->ship_to,
				   	"memo" 						=> $obj->memo,
				   	"memo2" 					=> $obj->memo2,
				   	"note" 						=> $obj->note,
				   	"recurring_name" 			=> $obj->recurring_name,
				   	"start_date"				=> $obj->start_date,
					"frequency"					=> $obj->frequency,
					"month_option"				=> $obj->month_option,
					"interval" 					=> $obj->interval,
					"day" 						=> $obj->day,
					"week" 						=> $obj->week,
					"month" 					=> $obj->month,
				   	"status" 					=> intval($obj->status),
				   	"progress" 					=> $obj->progress,
				   	"is_recurring" 				=> floatval($obj->is_recurring),
				   	"is_journal" 				=> $obj->is_journal,
				   	"print_count" 				=> $obj->print_count,
				   	"printed_by" 				=> $obj->printed_by,
				   	"deleted" 					=> $obj->deleted,
				   	"meter_id"					=> $obj->meter_id,
				   	"amount_paid"				=> 0,
				   	"contact" 					=> $contact
			   	);
		    }
		}

		$data["count"] = count($data["results"]);
		$this->response($data, 201);
	}

	//PUT
	function index_put() {
		$models = json_decode($this->put('models'));
		$data["results"] = [];
		$data["count"] = 0;

		foreach ($models as $value) {
			$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$obj->get_by_id($value->id);

			//Remove previouse segments
			$segment = explode(",",$obj->segments);
			if(count($segment)>0){
		   		$prevSegments = new Segmentitem(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
		   		$prevSegments->where_in("id", $segment)->get();
		   		$obj->delete($prevSegments->all);
		   	}

			isset($value->company_id) 				? $obj->company_id 					= $value->company_id : "";
			isset($value->location_id) 				? $obj->location_id 				= $value->location_id : "";
			isset($value->contact_id) 				? $obj->contact_id 					= $value->contact_id : "";
			isset($value->payment_term_id) 			? $obj->payment_term_id 			= $value->payment_term_id : "";
			isset($value->payment_method_id) 		? $obj->payment_method_id 			= $value->payment_method_id : "";
			isset($value->transaction_template_id) 	? $obj->transaction_template_id 	= $value->transaction_template_id : "";
			isset($value->reference_id) 			? $obj->reference_id 				= $value->reference_id : "";
			isset($value->recurring_id) 			? $obj->recurring_id 				= $value->recurring_id : "";
			isset($value->return_id) 				? $obj->return_id 					= $value->return_id : "";
			isset($value->job_id) 					? $obj->job_id 						= $value->job_id : "";
			isset($value->account_id) 				? $obj->account_id 					= $value->account_id : "";
			isset($value->item_id) 					? $obj->item_id 					= $value->item_id : "";
			isset($value->tax_item_id) 				? $obj->tax_item_id 				= $value->tax_item_id : "";
			isset($value->wht_account_id) 			? $obj->wht_account_id 				= $value->wht_account_id : "";
			isset($value->user_id) 					? $obj->user_id 					= $value->user_id : "";
			isset($value->employee_id) 				? $obj->employee_id 				= $value->employee_id : "";
			isset($value->number) 					? $obj->number 						= $value->number : "";
		   	isset($value->type) 					? $obj->type 						= $value->type : "";
		   	isset($value->journal_type) 			? $obj->journal_type 				= $value->journal_type : "";
		   	isset($value->sub_total) 				? $obj->sub_total 					= $value->sub_total : "";
		   	isset($value->discount) 				? $obj->discount 					= $value->discount : "";
		   	isset($value->tax) 						? $obj->tax 						= $value->tax : "";
		   	isset($value->amount) 					? $obj->amount 						= $value->amount : "";
		   	isset($value->fine) 					? $obj->fine 						= $value->fine : "";
		   	isset($value->deposit) 					? $obj->deposit 					= $value->deposit : "";
		   	isset($value->remaining) 				? $obj->remaining 					= $value->remaining : "";
		   	isset($value->received) 				? $obj->received 					= $value->received : "";
		   	isset($value->change) 					? $obj->change 						= $value->change : "";
		   	isset($value->credit_allowed) 			? $obj->credit_allowed 				= $value->credit_allowed : "";
		   	isset($value->additional_cost) 			? $obj->additional_cost 			= $value->additional_cost : "";
		   	isset($value->additional_apply) 		? $obj->additional_apply 			= $value->additional_apply : "";
		   	isset($value->rate) 					? $obj->rate 						= $value->rate : "";
		   	isset($value->locale) 					? $obj->locale 						= $value->locale : "";
		   	isset($value->month_of) 				? $obj->month_of 					= $value->month_of : "";
		   	isset($value->issued_date) 				? $obj->issued_date 				= $value->issued_date : "";
		   	isset($value->bill_date) 				? $obj->bill_date 					= $value->bill_date : "";
		   	isset($value->payment_date) 			? $obj->payment_date 				= $value->payment_date : "";
		   	isset($value->due_date) 				? $obj->due_date 					= $value->due_date : "";
		   	isset($value->deposit_date) 			? $obj->deposit_date 				= $value->deposit_date : "";
		   	isset($value->check_no) 				? $obj->check_no 					= $value->check_no : "";
		   	isset($value->reference_no) 			? $obj->reference_no 				= $value->reference_no : "";
		   	isset($value->references) 				? $obj->references 					= implode(",", $value->references) : "";
		   	isset($value->segments) 				? $obj->segments 					= implode(",", $value->segments) : "";
		   	isset($value->bill_to) 					? $obj->bill_to 					= $value->bill_to : "";
		   	isset($value->ship_to) 					? $obj->ship_to 					= $value->ship_to : "";
		   	isset($value->memo) 					? $obj->memo 						= $value->memo : "";
		   	isset($value->memo2) 					? $obj->memo2 						= $value->memo2 : "";
		   	isset($value->note) 					? $obj->note 						= $value->note : "";
		   	isset($value->recurring_name) 			? $obj->recurring_name 				= $value->recurring_name : "";
		   	isset($value->start_date) 				? $obj->start_date 					= $value->start_date : "";
		   	isset($value->frequency) 				? $obj->frequency 					= $value->frequency : "";
		   	isset($value->month_option) 			? $obj->month_option 				= $value->month_option : "";
		   	isset($value->interval) 				? $obj->interval 					= $value->interval : "";
		   	isset($value->day) 						? $obj->day 						= $value->day : "";
		   	isset($value->week) 					? $obj->week 						= $value->week : "";
		   	isset($value->month) 					? $obj->month 						= $value->month : "";
		   	isset($value->status) 					? $obj->status 						= $value->status : "";
		   	isset($value->progress) 				? $obj->progress 					= $value->progress : "";
		   	isset($value->is_recurring) 			? $obj->is_recurring 				= $value->is_recurring : "";
		   	isset($value->is_journal) 				? $obj->is_journal 					= $value->is_journal : "";
		   	isset($value->print_count) 				? $obj->print_count 				= $value->print_count : "";
		   	isset($value->printed_by) 				? $obj->printed_by 					= $value->printed_by : "";
		   	isset($value->deleted) 					? $obj->deleted 					= $value->deleted : "";
		   	isset($value->meter_id) 				? $obj->meter_id 					= $value->meter_id : "";
		   	
		   	//Update new segments
			if(isset($value->segments)){
				foreach ($value->segments as $sg) {
					$relatedsegmentitem = new Segmentitem(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$relatedsegmentitem->get_by_id($sg);
					$relatedsegmentitem->save($obj);
				}
			}

			if($obj->save()){
				//Results
				$data["results"][] = array(
					"id" 						=> $obj->id,
					"company_id" 				=> $obj->company_id,
					"location_id" 				=> $obj->location_id,
					"contact_id" 				=> $obj->contact_id,
					"payment_term_id" 			=> $obj->payment_term_id,
					"payment_method_id" 		=> $obj->payment_method_id,
					"transaction_template_id" 	=> $obj->transaction_template_id,
					"reference_id" 				=> $obj->reference_id,
					"recuring_id" 				=> $obj->recuring_id,
					"return_id" 				=> $obj->return_id,
					"job_id" 					=> $obj->job_id,
					"account_id" 				=> $obj->account_id,
					"item_id" 					=> $obj->item_id,
					"tax_item_id" 				=> $obj->tax_item_id,
					"wht_account_id"			=> $obj->wht_account_id,
					"user_id" 					=> $obj->user_id,
					"employee_id" 				=> $obj->employee_id,
					"number" 					=> $obj->number,
				   	"type" 						=> $obj->type,
				   	"journal_type" 				=> $obj->journal_type,
				   	"sub_total"					=> floatval($obj->sub_total),
				   	"discount" 					=> floatval($obj->discount),
				   	"tax" 						=> floatval($obj->tax),
				   	"amount" 					=> floatval($obj->amount),
				   	"fine" 						=> floatval($obj->fine),
				   	"deposit"					=> floatval($obj->deposit),
				   	"remaining" 				=> floatval($obj->remaining),
				   	"received" 					=> floatval($obj->received),
				   	"change" 					=> floatval($obj->change),
				   	"credit_allowed"			=> floatval($obj->credit_allowed),
				   	"additional_cost" 			=> floatval($obj->additional_cost),
				   	"additional_apply" 			=> $obj->additional_apply,
				   	"rate" 						=> floatval($obj->rate),
				   	"locale" 					=> $obj->locale,
				   	"month_of"					=> $obj->month_of,
				   	"issued_date"				=> $obj->issued_date,
				   	"bill_date"					=> $obj->bill_date,
				   	"payment_date" 				=> $obj->payment_date,
				   	"due_date" 					=> $obj->due_date,
				   	"deposit_date" 				=> $obj->deposit_date,
				   	"check_no" 					=> $obj->check_no,
				   	"reference_no" 				=> $obj->reference_no,
				   	"references" 				=> $obj->references!="" ? array_map('intval', explode(",", $obj->references)) : [],
				   	"segments" 					=> $obj->segments!="" ? array_map('intval', explode(",", $obj->segments)) : [],
				   	"bill_to" 					=> $obj->bill_to,
				   	"ship_to" 					=> $obj->ship_to,
				   	"memo" 						=> $obj->memo,
				   	"memo2" 					=> $obj->memo2,
				   	"note" 						=> $obj->note,
				   	"recurring_name" 			=> $obj->recurring_name,
				   	"start_date"				=> $obj->start_date,
					"frequency"					=> $obj->frequency,
					"month_option"				=> $obj->month_option,
					"interval" 					=> $obj->interval,
					"day" 						=> $obj->day,
					"week" 						=> $obj->week,
					"month" 					=> $obj->month,
				   	"status" 					=> intval($obj->status),
				   	"progress" 					=> $obj->progress,
				   	"is_recurring" 				=> floatval($obj->is_recurring),
				   	"is_journal" 				=> $obj->is_journal,
				   	"print_count" 				=> $obj->print_count,
				   	"printed_by" 				=> $obj->printed_by,
				   	"deleted" 					=> $obj->deleted,
				   	"meter_id"					=> $obj->meter_id,
				   	"amount_paid"				=> 0
				);
			}
		}
		$data["count"] = count($data["results"]);

		$this->response($data, 200);
	}

	//DELETE
	function index_delete() {
		$models = json_decode($this->delete('models'));

		foreach ($models as $key => $value) {
			$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$obj->get_by_id($value->id);

			$data["results"][] = array(
				"data"   => $value,
				"status" => $obj->delete()
			);
		}

		//Response data
		$this->response($data, 200);
	}	

    //Generate invoice number
	public function _generate_number($type, $date){
		$YY = date("y");
		$MM = date("m");
		$startDate = date("Y")."-01-01";
		$endDate = date("Y")."-12-31";

		if(isset($date)){
			$YY = date('y', strtotime($date));
			$MM = date('m', strtotime($date));
			$startDate = $YY."-01-01";
			$endDate = $YY."-12-31";
		}

		$prefix = new Prefix(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
		$prefix->where('type', $type);
		$prefix->limit(1);
		$prefix->get();

		$headerWithDate = $prefix->abbr . $YY . $MM;

		$txn = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
		$txn->where('type', $type);
		$txn->where("issued_date >=", $startDate);
		$txn->where("issued_date <=", $endDate);
		$txn->where('is_recurring <>', 1);
		$txn->order_by('id', 'desc');
		$txn->limit(1);
		$txn->get();

		$number = "";
		if($txn->exists()){
			$no = 0;
			if(strlen($txn->number)>10){
				$no = intval(substr($txn->number, strlen($txn->number) - 5));
			}
			$no++;

			$number = $headerWithDate . str_pad($no, 5, "0", STR_PAD_LEFT);
		}else{
			//Check existing txn
			$existTxn = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$existTxn->where('type', $type);
			$existTxn->where('is_recurring <>', 1);
			$existTxn->limit(1);
			$existTxn->get();

			if($existTxn->exists()){
				$number = $headerWithDate . str_pad(1, 5, "0", STR_PAD_LEFT);
			}else{
				$number = $headerWithDate . str_pad($prefix->startup_number, 5, "0", STR_PAD_LEFT);
			}
		}

		return $number;
	}

	//POST WITH LINE
	function with_line_post() {
		$models = json_decode($this->post('models'));
		$data["results"] = [];
		$data["count"] = 0;
		
		$number = "";
		foreach ($models as $value) {
			//Generate Number
			if(isset($value->number)){
				$number = $value->number;

				if($number==""){
					$number = $this->_generate_number($value->type, $value->issued_date);
				}
			}else{
				$number = $this->_generate_number($value->type, $value->issued_date);
			}
			
			if(isset($value->is_recurring)){
				if($value->is_recurring==1){
					$number = "";
				}
			}

			$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			isset($value->company_id) 				? $obj->company_id 					= $value->company_id : "";
			isset($value->location_id) 				? $obj->location_id 				= $value->location_id : "";
			isset($value->contact_id) 				? $obj->contact_id 					= $value->contact_id : "";
			isset($value->payment_term_id) 			? $obj->payment_term_id 			= $value->payment_term_id : 5;
			isset($value->payment_method_id) 		? $obj->payment_method_id 			= $value->payment_method_id : "";
			isset($value->transaction_template_id) 	? $obj->transaction_template_id 	= $value->transaction_template_id : "";
			isset($value->reference_id) 			? $obj->reference_id 				= $value->reference_id : "";
			isset($value->recurring_id) 			? $obj->recurring_id 				= $value->recurring_id : "";
			isset($value->return_id) 				? $obj->return_id 					= $value->return_id : "";
			isset($value->job_id) 					? $obj->job_id 						= $value->job_id : "";
			isset($value->account_id) 				? $obj->account_id 					= $value->account_id : "";
			isset($value->item_id) 					? $obj->item_id 					= $value->item_id : "";
			isset($value->tax_item_id) 				? $obj->tax_item_id 				= $value->tax_item_id : "";
			isset($value->wht_account_id) 			? $obj->wht_account_id 				= $value->wht_account_id : "";
			isset($value->user_id) 					? $obj->user_id 					= $value->user_id : "";
			isset($value->employee_id) 				? $obj->employee_id 				= $value->employee_id : "";
			$obj->number = $number;
		   	isset($value->type) 					? $obj->type 						= $value->type : "";
		   	isset($value->journal_type) 			? $obj->journal_type 				= $value->journal_type : "";
		   	isset($value->sub_total) 				? $obj->sub_total 					= $value->sub_total : "";
		   	isset($value->discount) 				? $obj->discount 					= $value->discount : "";
		   	isset($value->tax) 						? $obj->tax 						= $value->tax : "";
		   	isset($value->amount) 					? $obj->amount 						= $value->amount : "";
		   	isset($value->fine) 					? $obj->fine 						= $value->fine : "";
		   	isset($value->deposit) 					? $obj->deposit 					= $value->deposit : "";
		   	isset($value->remaining) 				? $obj->remaining 					= $value->remaining : "";
		   	isset($value->received) 				? $obj->received 					= $value->received : "";
		   	isset($value->change) 					? $obj->change 						= $value->change : "";
		   	isset($value->credit_allowed) 			? $obj->credit_allowed 				= $value->credit_allowed : "";
		   	isset($value->additional_cost) 			? $obj->additional_cost 			= $value->additional_cost : "";
		   	isset($value->additional_apply) 		? $obj->additional_apply 			= $value->additional_apply : "";
		   	isset($value->rate) 					? $obj->rate 						= $value->rate : "";
		   	isset($value->locale) 					? $obj->locale 						= $value->locale : "";
		   	isset($value->month_of) 				? $obj->month_of 					= $value->month_of : "";
		   	isset($value->issued_date) 				? $obj->issued_date 				= $value->issued_date : "";
		   	isset($value->bill_date) 				? $obj->bill_date 					= $value->bill_date : "";
		   	isset($value->payment_date) 			? $obj->payment_date 				= $value->payment_date : "";
		   	isset($value->due_date) 				? $obj->due_date 					= $value->due_date : "";
		   	isset($value->deposit_date) 			? $obj->deposit_date 				= $value->deposit_date : "";
		   	isset($value->check_no) 				? $obj->check_no 					= $value->check_no : "";
		   	isset($value->reference_no) 			? $obj->reference_no 				= $value->reference_no : "";
		   	isset($value->references) 				? $obj->references 					= implode(",", $value->references) : "";
		   	isset($value->segments) 				? $obj->segments 					= implode(",", $value->segments) : "";
		   	isset($value->bill_to) 					? $obj->bill_to 					= $value->bill_to : "";
		   	isset($value->ship_to) 					? $obj->ship_to 					= $value->ship_to : "";
		   	isset($value->memo) 					? $obj->memo 						= $value->memo : "";
		   	isset($value->memo2) 					? $obj->memo2 						= $value->memo2 : "";
		   	isset($value->recurring_name) 			? $obj->recurring_name 				= $value->recurring_name : "";
		   	isset($value->start_date) 				? $obj->start_date 					= $value->start_date : "";
		   	isset($value->frequency) 				? $obj->frequency 					= $value->frequency : "";
		   	isset($value->month_option) 			? $obj->month_option 				= $value->month_option : "";
		   	isset($value->interval) 				? $obj->interval 					= $value->interval : "";
		   	isset($value->day) 						? $obj->day 						= $value->day : "";
		   	isset($value->week) 					? $obj->week 						= $value->week : "";
		   	isset($value->month) 					? $obj->month 						= $value->month : "";
		   	isset($value->status) 					? $obj->status 						= $value->status : "";
		   	isset($value->progress) 				? $obj->progress 					= $value->progress : "";
		   	isset($value->is_recurring) 			? $obj->is_recurring 				= $value->is_recurring : "";
		   	isset($value->is_journal) 				? $obj->is_journal 					= $value->is_journal : "";
		   	isset($value->print_count) 				? $obj->print_count 				= $value->print_count : "";
		   	isset($value->printed_by) 				? $obj->printed_by 					= $value->printed_by : "";
		   	isset($value->deleted) 					? $obj->deleted 					= $value->deleted : "";
		   	isset($value->meter_id) 				? $obj->meter_id 					= $value->meter_id : "";
		   	
	   		if($obj->save()){
	   			$data["results"][] = $obj->where("id", $obj->id)->get_raw()->result()[0];

	   			//Lines
			   	if(isset($value->lines)){
			   		foreach ($value->lines as $val) {
			   			$lines = new Item_line(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

			   			$lines->transaction_id 	= $obj->id;
						isset($val->item_id)			? $lines->item_id			= $val->item_id : "";
						isset($val->assembly_id)		? $lines->assembly_id 		= $val->assembly_id : "";
						isset($val->measurement_id)		? $lines->measurement_id	= $val->measurement_id : "";
						isset($val->tax_item_id)		? $lines->tax_item_id		= $val->tax_item_id : "";
					   	isset($val->wht_account_id)		? $lines->wht_account_id	= $val->wht_account_id : "";
					   	isset($val->description)		? $lines->description 		= $val->description : "";
					   	isset($val->on_hand)			? $lines->on_hand 			= $val->on_hand : "";
					   	// isset($val->on_po)			? $lines->on_po 			= $val->on_po : "";
					   	// isset($val->on_so)			? $lines->on_so 			= $val->on_so : "";
					   	isset($val->gross_weight)		? $lines->gross_weight 		= $val->gross_weight : "";
					   	isset($val->truck_weight)		? $lines->truck_weight 		= $val->truck_weight : "";
					   	isset($val->bag_weight)			? $lines->bag_weight 		= $val->bag_weight : "";
					   	isset($val->yield)				? $lines->yield 			= $val->yield : "";
					   	isset($val->quantity)			? $lines->quantity 			= $val->quantity : "";
					   	isset($val->quantity_adjusted) 	? $lines->quantity_adjusted = $val->quantity_adjusted : "";
					   	// isset($val->conversion_ratio)? $lines->conversion_ratio 	= $val->conversion_ratio : $lines->conversion_ratio = 1;
					   	isset($val->cost)				? $lines->cost 				= $val->cost : "";
					   	isset($val->price)				? $lines->price 			= $val->price : "";
					   	//isset($val->price_avg)		? $lines->price_avg 		= $val->price_avg : "";		   	
					   	isset($val->amount)				? $lines->amount 			= $val->amount : "";
					   	isset($val->markup)				? $lines->markup 			= $val->markup : "";
					   	isset($val->discount)			? $lines->discount 			= $val->discount : "";
					   	isset($val->fine)				? $lines->fine 				= $val->fine : "";
					   	isset($val->tax)				? $lines->tax 				= $val->tax : "";
					   	isset($val->rate)				? $lines->rate 				= $val->rate : "";
					   	isset($val->locale)				? $lines->locale 			= $val->locale : "";
					   	isset($val->additional_cost)	? $lines->additional_cost  	= $val->additional_cost : "";
					   	isset($val->additional_applied)	? $lines->additional_applied= $val->additional_applied : "";
					   	isset($val->movement)			? $lines->movement 			= $val->movement : "";
					   	isset($val->required_date)		? $lines->required_date 	= $val->required_date : "";
					   	isset($val->deleted) 			? $lines->deleted 			= $val->deleted : "";

					   	if($lines->save()){
					   		$data["lines"][] = $lines->where("id", $lines->id)->get_raw()->result()[0];
					   	}
			   		}
			   	}
		    }
		}

		$data["count"] = count($data["results"]);
		$this->response($data, 201);
	}

	//GET BALANCE
	function balance_get() {
		$filter 	= $this->get("filter");
		$data["results"] = [];
		$data["count"] = 1;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Filter
		if(!empty($filter) && isset($filter)){
	    	foreach ($filter["filters"] as $value) {
	    		if(isset($value["operator"])) {
					$obj->{$value["operator"]}($value["field"], $value["value"]);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}
		
		// $obj->like("type", "Invoice", "before");
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring <>", 1);
		$obj->where("deleted <>", 1);
		$obj->get_iterated();

		$ids = [];
		$sum = 0;
		if($obj->exists()){
			foreach ($obj as $value) {
				array_push($ids, $value->id);
				$sum += floatval($value->amount) - floatval($value->deposit);
			}
		}

		//Paid
		if(count($ids)>0){
			$receipt = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$receipt->where_in("reference_id", $ids);
			$receipt->where_in("type", array("Cash_Receipt","Offset_Invoice","Cash_Payment","Offset_Bill"));
			$receipt->where("deleted <>", 1);
			$receipt->get_iterated();

			if($receipt->exists()){
				foreach ($receipt as $value) {
					$sum -= floatval($value->amount) + floatval($value->discount);
				}
			}
		}

		$data["results"][] = array("amount"=>$sum);

		//Response Data
		$this->response($data, 200);
	}

	//GET AMOUNT SUM
	function amount_sum_get() {
		$filters 	= $this->get("filter")["filters"];
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$is_recurring = 0;
		$deleted = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Filter
		if(!empty($filters) && isset($filters)){
	    	foreach ($filters as $value) {
	    		if(!empty($value["operator"]) && isset($value["operator"])){
		    		if($value["operator"]=="where_in"){
		    			$obj->where_in($value["field"], $value["value"]);
		    		}else if($value["operator"]=="or_where_in"){
		    			$obj->or_where_in($value["field"], $value["value"]);
		    		}else if($value["operator"]=="where_not_in"){
		    			$obj->where_not_in($value["field"], $value["value"]);
		    		}else if($value["operator"]=="or_where_not_in"){
		    			$obj->or_where_not_in($value["field"], $value["value"]);
		    		}else if($value["operator"]=="like"){
		    			$obj->like($value["field"], $value["value"]);
		    		}else if($value["operator"]=="or_like"){
		    			$obj->or_like($value["field"], $value["value"]);
		    		}else if($value["operator"]=="not_like"){
		    			$obj->not_like($value["field"], $value["value"]);
		    		}else if($value["operator"]=="or_not_like"){
		    			$obj->or_not_like($value["field"], $value["value"]);
		    		}else if($value["operator"]=="startswith"){
		    			$obj->like($value["field"], $value["value"], "after");
		    		}else if($value["operator"]=="endswith"){
		    			$obj->like($value["field"], $value["value"], "before");
		    		}else if($value["operator"]=="contains"){
		    			$obj->like($value["field"], $value["value"], "both");
		    		}else if($value["operator"]=="or_where"){
		    			$obj->or_where($value["field"], $value["value"]);
		    		}else{
		    			$obj->where($value["field"].' '.$value["operator"], $value["value"]);
		    		}
	    		}else{
	    			if($value["field"]=="is_recurring"){
	    				$is_recurring = $value["value"];
	    			}else if($value["field"]=="deleted"){
	    				$deleted = $value["value"];
	    			}else{
	    				$obj->where($value["field"], $value["value"]);
	    			}
	    		}
			}
		}

		$obj->select_sum("amount");
		$obj->where("is_recurring", $is_recurring);
		$obj->where("deleted", $deleted);
		$obj->get();

		$data["results"][] = array(
			"amount" => floatval($obj->amount)
		);

		//Response Data
		$this->response($data, 200);
	}

	//GET STATEMENT
	function statement_get() {
		$filters 	= $this->get("filter")["filters"];
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;
		$startDate = "";
		$typeList = array("Commercial_Invoice", "Vat_Invoice", "Invoice", "Commercial_Cash_Sale", "Vat_Cash_Sale", "Cash_Sale", "Deposit", "Cash_Receipt", "Sale_Return");

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter
		if(!empty($filters) && isset($filters)){
	    	foreach ($filters as $value) {
	    		$obj->where($value["field"], $value["value"]);

	    		if($value["field"]=="issued_date >=" || $value["field"]=="issued_date"){
	    			$startDate = $value["value"];
	    		}
			}
		}

		$obj->where_in("type", $typeList);
		$obj->where("is_recurring", 0);
		$obj->where("deleted", 0);
		$obj->order_by("issued_date", "asc");
		$obj->order_by("number", "asc");
		$obj->get_iterated();

		//Balance Forward
		$balance = 0;
		if($startDate!==""){
			$bf = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$bf->where("issued_date <", $startDate);
			$bf->where_in("type", $typeList);
			$bf->where("is_recurring", 0);
			$bf->where("deleted", 0);
			$bf->get_iterated();

			foreach ($bf as $value) {
				$balance += floatval($value->amount) - floatval($value->deposit);
			}

		    $bfDate = strtotime($startDate);
		    $bfDate = strtotime("-1 day", $bfDate);

			$data["results"][] = array(
				"id" 				=> 0,
				"issued_date"		=> date('Y-m-d', $bfDate),
				"type" 				=> "Balance Forward",
				"job" 				=> "",
			   	"reference_no" 		=> "",
			   	"amount" 			=> $balance,
			   	"balance" 			=> $balance,
			   	"rate" 				=> $bf->rate,
			   	"locale" 			=> $bf->locale
			);
		}

		if($obj->exists()){
			foreach ($obj as $value) {
				$amount = floatval($value->amount) - floatval($value->deposit);
				$balance += $amount;

				$data["results"][] = array(
					"id" 				=> 0,
					"issued_date"		=> $value->issued_date,
					"type" 				=> $value->type,
					"job" 				=> $value->job->get()->name,
				   	"reference_no" 		=> $value->number,
				   	"amount" 			=> $amount,
				   	"balance" 			=> $balance,
				   	"rate" 				=> $value->rate,
				   	"locale" 			=> $value->locale
				);
			}
		}

		//Response Data
		$this->response($data, 200);
	}

	//GET STATEMENT AGING
	function statement_aging_get() {
		$filters 	= $this->get("filter")["filters"];
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;
		$sort 	 	= $this->get("sort");
		$data["results"] = [];
		$data["count"] = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Filter
		if(!empty($filters) && isset($filters)){
	    	foreach ($filters as $value) {
	    		$obj->where($value["field"], $value["value"]);

	    		if($value["field"]=="issued_date >=" || $value["field"]=="issued_date"){
	    			$startDate = $value["value"];
	    		}
			}
		}

		$obj->where_in("type", ["Commercial_Invoice", "Vat_Invoice", "Invoice"]);
		$obj->where_in("status", array(0,2));
		$obj->where("is_recurring", 0);
		$obj->where("deleted", 0);
		$obj->get_iterated();

		$amount = 0;
		$current = 0;
		$oneMonth = 0;
		$twoMonth = 0;
		$threeMonth = 0;
		$overMonth = 0;
		$locale = "";
		if($obj->exists()){
			foreach ($obj as $value) {
				$today = new DateTime();
				$dueDate = new DateTime($value->due_date);
				$days = $dueDate->diff($today)->format("%a");

				$amount += floatval($value->amount);
				$locale = $value->locale;

				if($dueDate < $today){
					if(intval($days)>90){
						$overMonth += floatval($value->amount);
					}else if(intval($days)>60){
						$threeMonth += floatval($value->amount);
					}else if(intval($days)>30){
						$twoMonth += floatval($value->amount);
					}else{
						$oneMonth += floatval($value->amount);
					}
				}else{
					$current += floatval($value->amount);
				}

			}
		}

		$data["results"][] = array(
			"id" 			=> 0,
			"current" 		=> $current,
			"oneMonth" 		=> $oneMonth,
			"twoMonth" 		=> $twoMonth,
			"threeMonth" 	=> $threeMonth,
			"overMonth" 	=> $overMonth,
			"amount" 		=> $amount,
			"locale" 		=> $locale
		);

		//Response Data
		$this->response($data, 200);
	}

	//BY CHOEUN
	//TXN PRINT GET --> Choeun
	function txn_print_get() {
		$filters 	= $this->get("filter")["filters"];
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;
		$sort 	 	= $this->get("sort");
		$data["results"] = array();
		$data["count"] = 0;
		$is_recurring = 0;

		$obj = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter
		if(!empty($filters) && isset($filters)){
	    	foreach ($filters as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
					if($value["field"]=="is_recurring"){
	    				$is_recurring = $value["value"];
	    			}else{
	    				$obj->where($value["field"], $value["value"]);
	    			}
				}
			}
		}

		$obj->where("is_recurring", $is_recurring);
		$obj->where("deleted <>", 0);

		//Results
		$obj->get_paged_iterated($page, $limit);
		$data["count"] = $obj->paged->total_rows;

		if($obj->exists()){
			foreach ($obj as $value) {

				//Sum amount paid
				$amount_paid = 0;
				if($value->type=="Commercial_Invoice" || $value->type=="Vat_Invoice" || $value->type=="Invoice" || $value->type=="Credit_Purchase" || $value->type=="Cash_Receipt" || $value->type=="Cash_Payment"){
					$paid = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$paid->select_sum("amount");
					$paid->select_sum("discount");
					$paid->where_in("type", array("Cash_Receipt", "Cash_Payment"));
					if($value->type=="Cash_Receipt" || $value->type=="Cash_Payment"){
						$paid->where("reference_id", $value->reference_id);
						$paid->where_not_in("id", array($value->id));
					}else{
						$paid->where("reference_id", $value->id);
					}
					$paid->where("is_recurring <>", 1);
					$paid->where("deleted <>", 1);
					$paid->get();
					$amount_paid = floatval($paid->amount) + floatval($paid->discount);
				}

				$data["results"][] = array(
					"id" 						=> $value->id,
					"company_id" 				=> $value->company_id,
					"location_id" 				=> $value->location_id,
					"contact_id" 				=> $value->contact_id,
					"payment_term_id" 			=> $value->payment_term_id,
					"payment_method_id" 		=> $value->payment_method_id,
					"transaction_template_id" 	=> $value->transaction_template_id,
					"reference_id" 				=> $value->reference_id,
					"recurring_id" 				=> $value->recurring_id,
					"return_id" 				=> $value->return_id,
					"job_id" 					=> $value->job_id,
					"account_id" 				=> $value->account_id,
					"item_id" 					=> $value->item_id,
					"tax_item_id" 				=> $value->tax_item_id,
					"user_id" 					=> $value->user_id,
					"employee_id" 				=> $value->employee_id,
				   	"number" 					=> $value->number,
				   	"reference_no" 				=> $value->reference_no,
				   	"type" 						=> $value->type,
				   	"journal_type" 				=> $value->journal_type,
				   	"sub_total"					=> floatval($value->sub_total),
				   	"discount" 					=> floatval($value->discount),
				   	"tax" 						=> floatval($value->tax),
				   	"amount" 					=> floatval($value->amount),
				   	"fine" 						=> floatval($value->fine),
				   	"deposit"					=> floatval($value->deposit),
				   	"remaining" 				=> floatval($value->remaining),
				   	"credit_allowed"			=> floatval($value->credit_allowed),
				   	"additional_cost" 			=> floatval($value->additional_cost),
				   	"additional_apply" 			=> $value->additional_apply,
				   	"rate" 						=> floatval($value->rate),
				   	"locale" 					=> $value->locale,
				   	"month_of"					=> $value->month_of,
				   	"issued_date"				=> $value->issued_date,
				   	"bill_date"					=> $value->bill_date,
				   	"payment_date" 				=> $value->payment_date,
				   	"due_date" 					=> $value->due_date,
				   	"deposit_date" 				=> $value->deposit_date,
				   	"check_no" 					=> $value->check_no,
				   	"segments" 					=> explode(",", $value->segments),
				   	"bill_to" 					=> $value->bill_to,
				   	"ship_to" 					=> $value->ship_to,
				   	"memo" 						=> $value->memo,
				   	"memo2" 					=> $value->memo2,
				   	"recurring_name" 			=> $value->recurring_name,
				   	"start_date"				=> $value->start_date,
				   	"frequency"					=> $value->frequency,
					"month_option"				=> $value->month_option,
					"interval" 					=> $value->interval,
					"day" 						=> $value->day,
					"week" 						=> $value->week,
					"month" 					=> $value->month,
				   	"status" 					=> $value->status,
				   	"is_recurring" 				=> $value->is_recurring,
				   	"is_journal" 				=> $value->is_journal,
				   	"print_count" 				=> $value->print_count,
				   	"printed_by" 				=> $value->printed_by,
				   	"deleted" 					=> $value->deleted,

				   	"contact" 					=> $value->contact->get_raw()->result(),
				   	"reference" 				=> $value->reference->get_raw()->result(),
				   	"amount_paid"				=> $amount_paid,
				   	"payment_term" 				=> $value->payment_term->get_raw()->result(),
				   	"payment_method" 			=> $value->payment_method->get_raw()->result()

				);
			}
		}

		//Response Data
		$this->response($data, 200);
	}

	//ITMES LINE PRINT GET --> Choeun
	function line_print_get() {
		$filters 	= $this->get("filter")["filters"];
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;
		$sort 	 	= $this->get("sort");
		$data["results"] = array();
		$data["count"] = 0;

		$obj = new Item_line(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

		//Sort
		if(!empty($sort) && isset($sort)){
			foreach ($sort as $value) {
				$obj->order_by($value["field"], $value["dir"]);
			}
		}

		//Filter
		if(!empty($filters) && isset($filters)){
	    	foreach ($filters as $value) {
	    		if(isset($value['operator'])) {
					$obj->{$value['operator']}($value['field'], $value['value']);
				} else {
	    			$obj->where($value["field"], $value["value"]);
				}
			}
		}

		//Results
		$obj->get_paged_iterated($page, $limit);
		$data["count"] = $obj->paged->total_rows;

		if($obj->result_count()>0){
			foreach ($obj as $value) {
				$itemPrice = [];
				if($value->item_id>0){
					$pl = new Item_price(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
					$pl->where("item_id", $value->item_id);
					$pl->get();
					foreach ($pl as $p) {
						$itemPrice[] = array(
							"id" 			=> $p->id,
							"item_id" 		=> $p->item_id,
							"assembly_id"	=> $p->assembly_id,
							"measurement_id"=> $p->measurement_id,
							"quantity"		=> floatval($p->quantity),
							"conversion_ratio" 	=> floatval($p->conversion_ratio),
							"price" 		=> floatval($p->price),
							"amount" 		=> floatval($p->amount),
							"locale" 		=> $p->locale,

							"measurement" 	=> $p->measurement->get()->name
						);
					}
				}

				$data["results"][] = array(
					"id" 				=> $value->id,
			   		"transaction_id"	=> $value->transaction_id,
			   		"measurement_id" 	=> $value->measurement_id,
					"tax_item_id" 		=> $value->tax_item_id,
					"item_id" 			=> $value->item_id,
				   	"description" 		=> $value->description,
				   	"on_hand" 			=> floatval($value->on_hand),
					"on_po" 			=> floatval($value->on_po),
					"on_so" 			=> floatval($value->on_so),
					"quantity" 			=> floatval($value->quantity),
				   	"quantity_adjusted" => floatval($value->quantity_adjusted),
				   	"cost"				=> floatval($value->cost),
				   	"price"				=> floatval($value->price),
				   	"price_avg" 		=> floatval($value->price_avg),
				   	"amount" 			=> floatval($value->amount),
				   	"discount" 			=> floatval($value->discount),
				   	"fine" 				=> floatval($value->fine),
				   	"additional_cost" 	=> floatval($value->additional_cost),
				   	"additional_applied"=> $value->additional_applied,
				   	"rate"				=> floatval($value->rate),
				   	"locale" 			=> $value->locale,
				   	"movement" 			=> $value->movement,
				   	"required_date"		=> $value->required_date,

				   	"item_prices" 		=> $itemPrice,
				   	"item" 		=> $value->item->get_raw()->result(),
				   	"journal" 			=> $value->journal->get_raw()->result()
				);
			}
		}
		$this->response($data, 200);
	}
	
}
/* End of file transactions.php */
/* Location: ./application/controllers/api/transaction.php */
