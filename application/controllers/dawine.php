<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dawine extends MY_Controller {
	
	function __construct() {
		parent::__construct();
		// if(!$this->session->userdata('logged_in')) {
		// 	redirect('home');
		// }
	}
	
	public function index() {
		/*
		 *set up title and keywords (if not the default in custom.php config file will be set) 
		 */
		$this->_render("dawine_view");	
	}

	public function custom_table() {
		/*
		 *set up title and keywords (if not the default in custom.php config file will be set) 
		 */
		$this->_render("custom_table_view");	
	}

}

/* End of file dawine.php */
/* Location: ./application/controllers/dawine.php */