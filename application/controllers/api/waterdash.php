<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); //disallow direct access to this file

require APPPATH.'/libraries/REST_Controller.php';

class Waterdash extends REST_Controller {	
	public $_database;
	public $server_host;
	public $server_user;
	public $server_pwd;
	public $startFiscalDate;
	public $endFiscalDate;
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

			//Fiscal Date
			$today = date("Y-m-d");
			$fdate = date("Y") ."-". $institute->fiscal_date;
			if($today > $fdate){
				$this->startFiscalDate 	= date("Y") ."-". $institute->fiscal_date;
				$this->endFiscalDate 	= date("Y",strtotime("+1 year")) ."-". $institute->fiscal_date;
			}else{
				$this->startFiscalDate 	= date("Y",strtotime("-1 year")) ."-". $institute->fiscal_date;
				$this->endFiscalDate 	= date("Y") ."-". $institute->fiscal_date;
			}
		}
	}

	function board_get() {}

	function license_get() {
		$filters 	= $this->get("filter")["filters"];		
		$page 		= $this->get('page') !== false ? $this->get('page') : 1;		
		$limit 		= $this->get('limit') !== false ? $this->get('limit') : 100;								
		$sort 	 	= $this->get("sort");		
		$data["results"] = array();
		$data["count"] = 0;

		$obj = new Branch(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);		

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
		$obj->where('type', 'w');
		// $obj->include_related('location', 'id');
		$obj->include_related_count('location');
		// $obj->include_related_count('location/contact');
		$obj->get();
		if($obj->exists()) {
			foreach($obj as $value) {
				$location = new Location(null, $this->server_host, $this->server_user, $this->server_pwd, 'db_banhji');
				$location->include_related('contact', array('id', 'status'));
				$location->where('branch_id', $value->id);

				$location->get();
				$activeCount = 0;
				$inActiveCount = 0;
<<<<<<< HEAD
				$sale = 0;
				foreach($location as $loc) {
					$trx = new Transaction(null, $this->server_host, $this->server_user, $this->server_pwd, 'db_banhji');
					$trx->select_sum('amount');
					$trx->where('location_id', $loc->id)->get();
					$sale += $trx->amount;
=======
				foreach($location as $loc) {
>>>>>>> origin/MOI
					if($loc->contact_status == 1) {
						$activeCount += 1;
					} else {
						$inActiveCount +=1;
					}					
				}
				// $line = new journal_line(null, $this->server_host, $this->server_user, $this->server_pwd, $this->_database);
				// $line->where('contact_id', $value->contact_id);
				// $line->where('account_id', $value->deposit_account_id);
				// $line->get();
				// $deposit = 0;
				// foreach($line as $l) {
				// 	if($l->dr != 0.00) {
				// 		$deposit += $l->dr;
				// 	} else {
				// 		$deposit -= $l->cr;
				// 	}
				// }

				// $contact = new Customer(null, $this->server_host, $this->server_user, $this->server_pwd, 'db_banhji');
				$data['results'][] = array(
					'id' => $value->id,
					'name'=>$value->name,
					'blocCount' => $value->location_count,
					'activeCustomer' => $activeCount,
					'inActiveCustomer' => $inActiveCount,
					'deposit' => 0,
					'usage' => 0,
					'sale' => $sale
				);
			}
			$this->response($data, 200);
		} else {
			$this->response($data, 400);
		}
	}
	
}
/* End of file waterdash.php */
/* Location: ./application/controllers/api/dashboards.php */