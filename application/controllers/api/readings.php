<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); //disallow direct access to this file

require APPPATH.'/libraries/REST_Controller.php';

class Readings extends REST_Controller {	
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
		// $this->_database = 'db_banhji';
	}
	
	//GET 
	function index_get() {		
		$filter 	= $this->get("filter");
		$page 		= $this->get('page');
		$limit 		= $this->get('limit');
		$sort 	 	= $this->get("sort");	
		$data["results"] = array();
		$data["count"] = 0;

		$obj = new Meter_record(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

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
	    		if(isset($value["operator"])) {
					//$obj->{$value["operator"]}($value["field"], $value["value"]);
				} else {
					if($value["field"]=="is_recurring"){
	    				$is_recurring = $value["value"];
	    			}else{
	    				$obj->where($value["field"], $value["value"]);
	    			}
				}
			}
		}			

		//Get Result
		$obj->order_by('created_at', 'desc');
		$obj->order_by('id', 'desc');
		$obj->where('deleted', 0);
		//Results
		if($page && $limit){
			$obj->get_paged_iterated($page, $limit);
			$data["count"] = $obj->paged->total_rows;
		}else{
			$obj->get_iterated();
			$data["count"] = $obj->result_count();
		}
		// $obj->get_paged_iterated($page, $limit);
		// $data["count"] = $obj->paged->total_rows;		

		if($obj->exists()){
			foreach ($obj as $value) {
				//Results
				$meter  = $value->meter->get();
				$data["results"][] = array(
					"id" 			=> $value->id,
					"meter_id" 		=> $value->meter_id,
					"branch_id" 	=> $meter->branch_id,
					"location_id" 	=> $meter->location_id,
					"previous"		=> $value->previous,
					"meter_id"		=> $meter->id,
					"current"		=> $value->current,
					"month_of"		=> $value->month_of,
					"from_date" 	=> $value->from_date,
					"to_date" 		=> $value->to_date,
					"date"			=> $value->from_date." - ".$value->to_date,
					"meter_number" 	=> $meter->number,
					"invoiced"   	=> $value->invoiced,
					"usage" 		=> $value->usage,
					"status"		=> "new",
					"_meta" 		=> array()
				);
			}
		} 

		//Response Data		
		$this->response($data, 200);		
	}
	
	//POST
	function index_post() {
		$models = json_decode($this->post('models'));

		foreach ($models as $value) {
			$obj = new Meter_record(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$meter = new Meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$meter->where('number', $value->meter_number)->get();
			$current = intval($value->current);
			$obj->meter_id 		= isset($meter->id)			? $meter->id : "";
			$obj->previous 		= isset($value->previous)	? $value->previous : "";
			$obj->current 		= isset($current)			? $current : "";
			$oldcurrent = 0;
			if($value->round == 1){
				$digit = $meter->number_digit;
				$oldcurrent =  pow(10, $digit);
				$oldcurrent = $oldcurrent - intval($value->previous);
				$obj->usage    = intval($oldcurrent) + intval($value->current);
				$meter->round += 1;
				$meter->save();
				$obj->new_round = 1;
			}else{
				$obj->usage    = intval($current) - intval($value->previous);
				$obj->new_round = 0;
			}
			$obj->from_date = isset($value->from_date) 	? date('Y-m-d', strtotime($value->from_date)) : date('Y-m-d');
			$obj->month_of 	= isset($value->month_of)	? date('Y-m-d', strtotime($value->month_of)): date('Y-m-d');
			$obj->to_date 	= isset($value->to_date)	? date('Y-m-d', strtotime($value->to_date)):date('Y-m-d');
			$obj->invoiced 	=  0;
			$obj->created_at = date('Y-m-d H:i:s');
			if($obj->save()){								
				//Respsone
				$data["results"][] = array(
					"id"			=> $obj->id,
					"meter_id" 		=> $obj->meter_id,
					"meter_number" 	=> $meter->number,
					"prev"			=> $obj->previous,
					"current"		=> $obj->current,
					"usage"	 		=> $obj->current - $obj->previous,
					"from_date"		=> $obj->from_date,
					"month_of"		=> $obj->month_of,
					"invoiced" 		=> $obj->invoiced,
					"to_date"		=> $obj->to_date,
					"usage" 		=> $obj->usage
				);				
			}			
		}
		$data["count"] = count($data["results"]);
		
		$this->response($data, 201);						
	}

	//PUT
	function index_put() {
		$models = json_decode($this->put('models'));
		$data["results"] = array();
		$data["count"] = 0;

		foreach ($models as $value) {			
			$obj = new Meter_record(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

			$obj->where('id', $value->id);
			$obj->get();
			$obj->current 				= isset($value->current)			?$value->current: "";
			$obj->from_date 			= isset($value->from_date)			?$value->from_date: "";
			$obj->to_date 				= isset($value->to_date)			?$value->to_date: "";
			$obj->meter_number 			= isset($value->meter_number)		?$value->meter_number: "";
			$obj->updated_at = date('Y-m-d H:i:s');
			$obj->usage 				= $obj->current - $obj->previous;
			if($obj->invoiced == 0) {
				// $obj->previous 				= isset($value->previous)			?$value->previous: "";
				
				$obj->sync = 2;
				if($obj->save()){
					$data["results"][] = array(
						"meter_id" 		=> $obj->meter_id,
						"meter_number" 	=> $obj->meter_number,
						"month_of"		=> $obj->month_of,
						"prev"			=> $obj->previous,
						"current"		=> $obj->current,
						"usage" 		=> $obj->usage,
						"from_date"		=> $obj->from_date,
						"to_date"		=> $obj->to_date,
						"_meta" 		=> array()
					);						
				}
			} else {
				$obj->invoiced = 0;
				$obj->sync = 2;
				$obj->save();

				$line = $obj->winvoice_line->get();

				$transaction = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);

				$winvoiceLine = new Winvoice_line(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
				$transaction->where('id', $line->transaction_id)->get();
				$transaction->deleted = 1;
				$transaction->save();

				$winvoiceLine->where('transaction_id', $line->transaction_id)->get();
				$winvoiceLine->deleted = 1;
				$winvoiceLine->save();

				$obj->meter_id 				= $value->meter_id;
				$obj->month_of 				= isset($value->month_of)			? date('Y-m-d', strtotime($value->month_of)): date('Y-m-d');
				$obj->previous 				= isset($value->previous)			? $value->previous: "";
				$obj->current 				= isset($value->current)			? $value->current: "";
				$obj->from_date 			= isset($value->previous_reading_date) ? date('Y-m-d', strtotime($value->previous_reading_date)) : "";
				$obj->to_date 				= isset($value->month_of)			? date('Y-m-d', strtotime($value->month_of)):date('Y-m-d');
				$obj->invoiced 				= 0;
				$obj->usage = $obj->current - $obj->previous;
				if($obj->save()){		
					$data["results"][] = array(
						"id"			=> $obj->id,
						"meter_id" 		=> $obj->meter_id,
						"month_of"		=> $obj->month_of,
						"meter_number" 	=> $obj->meter_number,
						"prev"			=> $obj->previous,
						"current"		=> $obj->current,
						"usage" 		=> $obj->usage,
						"from_date"		=> $obj->from_date,
						"to_date"		=> $obj->to_date
					);						
				}
				// }
			}
		}
		$data["count"] = count($data["results"]);

		$this->response($data, 200);
	}
	
	//DELETE
	function index_delete() {
		$models = json_decode($this->delete('models'));

		foreach ($models as $key => $value) {
			$obj = new Meter_record(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
			$obj->where("id", $value->id)->get();
			
			$data["results"][] = array(
				"data"   => $value,
				"deleted" => TRUE
			);
							
		}

		//Response data
		$this->response($data, 200);
	}

	function books_get() {		
		$filters 	= $this->get("filter")["filters"];		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit');								
		$sort 	 	= $this->get("sort");		
		$data["results"] = array();
		$data["count"] = 0;

		$obj = new Meter(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
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
			}									 			
		}
		//Get Result
		$obj->where('activated', 1);
		$obj->where('status', 1);	
		//Results
		$obj->order_by("worder", "asc");
		if($page && $limit){
			$obj->get_paged_iterated($page, $limit);
			$data["count"] = $obj->paged->total_rows;
		}else{
			$obj->get_iterated();
			$data["count"] = $obj->result_count();
		}
		if($obj->result_count()>0){			
			foreach ($obj as $value) {
				//Results
				$date     = null;
				$location = $value->location->get();
				$contact = $value->contact->get();
				$record = $value->record;
				if(!empty($filters) && isset($filters)){			
			    	foreach ($filters as $f) {
			    		if(!empty($f["operator"]) && isset($f["operator"])){
				    		$record->where("invoiced <>", 1);
			    		}
					}									 			
				}
				$record->limit(1)->order_by('id', 'desc')->get();	
				$data["meta"] = array(
					'location_id' => $location->id,
					'location_name' => $location->name,
					'location_abbr' => $location->abbr
				);
				if($record->exists()) {
					$data["results"][] = array(
						"_contact" 		=> $contact->name,
						"meter_id" 		=> $value->id,
						"meter_number" 	=> $value->number,
						"previous"		=> floatval($record->current),
						"current"		=> 0,
						"from_date"		=> $record->from_date, 
						"to_date"		=> $record->to_date,
						"month_of" 		=> $record->month_of,
						"status" 		=> "new"
					);
				}
			}
		}
		$data["count"] = count($data['results']);
		//Response Data		
		$this->response($data, 200);		
	}
}
/* End of file meters.php */
/* Location: ./application/controllers/api/meters.php */