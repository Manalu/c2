<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Admin extends CI_Controller {
	
	function __construct() {
		parent::__construct();
	}
	
	public function index() {	
		$this->load->view("admin/admin_view");	
	}
}

/* End of file home.php */
/* Location: ./application/controllers/home.php */