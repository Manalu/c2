<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Water extends CI_Controller {
	
	function __construct() {
		parent::__construct();
	}
	
	public function index() {	
		$this->load->view("template/utibill-header");
		// $this->_render("utibill_view");
		$this->load->view("water_view");
		$this->load->view("template/utibill-footer");
	}
}

/* End of file home.php */
/* Location: ./application/controllers/utibill_view.php */