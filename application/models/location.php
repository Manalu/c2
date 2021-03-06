<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Location extends DataMapper {
	protected $created_field = "created_at";
	protected $updated_field = "updated_at";

	public $has_one = array(
		"warehouse" => array(
			"class" => "warehouse", 
			"other_field" => "location"
		),
		"location_type" => array(
			"class" => "location_type", 
			"other_field" => "location"
		),
		"branch" => array(
			"class" => "branch", 
			"other_field" => "location"
		),
		"contact_utility" => array(
			"class" => "contact_utility", 
			"other_field" => "location"
		)	
	);

	public $has_many = array(
		'transaction' => array(
			"class" => 'transaction',
			"other_field" => "location"
		),
		"meter" => array(
			'class' => "meter",
			'other_field' => 'location'
		),
		"electricity_box" => array(
			'class' => "electricity_box",
			'other_field' => 'location'
		),
		'contact' => array(
			'class' => 'customer',
			'other_field' => 'location'
		),
		'bin_location' => array(
			'class' => 'bin_location',
			'other_field' => 'location'
		)
	);

	public function __construct($id = null, $server_name = null, $db_username = null, $server_password = null, $db = null) {	
		$this->db_params = array(
				'dbdriver' => 'mysql',
				'pconnect' => true,
				'db_debug' => true,
				'cache_on' => false,
				'char_set' => 'utf8',
				'cachedir' => '',
				'dbcollat' => 'utf8_general_ci',
				'hostname' => 'banhji-db-instance.cwxbgxgq7thx.ap-southeast-1.rds.amazonaws.com',
				'username' => 'mightyadmin',
				'password' => 'banhji2016',
				'database' => $db,
				'prefix'   => ''
			);
		parent::__construct($id);
	}
}

/* End of file location.php */
/* Location: ./application/models/location.php */