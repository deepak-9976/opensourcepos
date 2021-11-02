<?php

namespace App\Controllers;

use app\Libraries\Tax_lib;

use app\Models\Appconfig;
use app\Models\enums\Rounding_mode;
use app\Models\Tax;
use app\Models\Tax_category;
use app\Models\Tax_code;
use app\Models\Tax_jurisdiction;

/**
 *
 *
 * @property tax_lib tax_lib
 *
 * @property appconfig appconfig
 * @property rounding_mode rounding_mode
 * @property tax tax
 * @property tax_category tax_category
 * @property tax_code tax_code
 * @property tax_jurisdiction tax_jurisdiction
 * 
 */
class Taxes extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('taxes');

		$this->appconfig = model('Appconfig');
		$this->rounding_mode = model('enums/Rounding_mode');
		$this->tax = model('Tax');
		$this->tax_category = model('Tax_category');
		$this->tax_code = model('Tax_code');
		$this->tax_jurisdiction = model('Tax_jurisdiction');
		
		$this->tax_lib = new Tax_lib();

		helper('tax_helper');
	}

	public function index(): void
	{
		$data['tax_codes'] = $this->xss_clean($this->tax_code->get_all()->getResultArray());
		if (count($data['tax_codes']) == 0)
		{
			$data['tax_codes'] = $this->tax_code->get_empty_row();
		}
		$data['tax_categories'] = $this->xss_clean($this->tax_category->get_all()->getResultArray());
		if (count($data['tax_categories']) == 0)
		{
			$data['tax_categories'] = $this->tax_category->get_empty_row();
		}
		$data['tax_jurisdictions'] = $this->xss_clean($this->tax_jurisdiction->get_all()->getResultArray());
		if (count($data['tax_jurisdictions']) == 0)
		{
			$data['tax_jurisdictions'] = $this->tax_jurisdiction->get_empty_row();
		}
		$data['tax_rate_table_headers'] = $this->xss_clean(get_tax_rates_manage_table_headers());
		$data['tax_categories_table_headers'] = $this->xss_clean(get_tax_categories_table_headers());
		$data['tax_types'] = $this->tax_lib->get_tax_types();

		if($this->appconfig->get('tax_included') == '1')
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		$data['tax_type_options'] = $this->tax_lib->get_tax_type_options($data['default_tax_type']);

		echo view('taxes/manage', $data);
	}


	/*
	Returns tax_codes table data rows. This will be called with AJAX.
	*/
	public function search(): void
	{
		$search = $this->request->getGet('search');
		$limit = $this->request->getGet('limit');
		$offset = $this->request->getGet('offset');
		$sort = $this->request->getGet('sort');
		$order = $this->request->getGet('order');

		$tax_rates = $this->tax->search($search, $limit, $offset, $sort, $order);

		$total_rows = $this->tax->get_found_rows($search);

		$data_rows = [];
		foreach($tax_rates->getResult() as $tax_rate_row)
		{
			$data_rows[] = $this->xss_clean(get_tax_rates_data_row($tax_rate_row));
		}

		echo json_encode (['total' => $total_rows, 'rows' => $data_rows]);
	}

	/*
	Gives search suggestions based on what is being searched for
	*/
	public function suggest_search(): void
	{
		$suggestions = $this->xss_clean($this->tax->get_search_suggestions($this->request->getPost('term')));	//TODO: There is no get_search_suggestions function in the tax model

		echo json_encode($suggestions);
	}

	/*
	Provides list of tax categories to select from
	*/
	public function suggest_tax_categories(): void
	{
		$suggestions = $this->xss_clean($this->tax_category->get_tax_category_suggestions($this->request->getPost('term')));

		echo json_encode($suggestions);
	}


	public function get_row(int $row_id): void
	{
		$data_row = $this->xss_clean(get_tax_rates_data_row($this->tax->get_info($row_id), $this));

		echo json_encode($data_row);
	}

	public function view_tax_codes(int $tax_code = -1): void	//TODO: Replace -1 with constant
	{
		$tax_code_info = $this->tax->get_info($tax_code);

		$default_tax_category_id = 1; // Tax category id is always the default tax category	//TODO: Replace 1 with constant
		$default_tax_category = $this->tax->get_tax_category($default_tax_category_id);

		$tax_rate_info = $this->tax->get_rate_info($tax_code, $default_tax_category_id);

		if($this->appconfig->get('tax_included') == '1')
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		$data['rounding_options'] = rounding_mode::get_rounding_options();
		$data['html_rounding_options'] = $this->get_html_rounding_options();

		if($tax_code == -1)	//TODO: Replace -1 with constant
		{//TODO: Duplicated code
			$data['tax_code'] = '';
			$data['tax_code_name'] = '';
			$data['tax_code_type'] = '0';
			$data['city'] = '';
			$data['state'] = '';
			$data['tax_rate'] = '0.0000';
			$data['rate_tax_code'] = '';
			$data['rate_tax_category_id'] = 1;
			$data['tax_category'] = '';
			$data['add_tax_category'] = '';
			$data['rounding_code'] = '0';
		}
		else
		{
			$data['tax_code'] = $tax_code;
			$data['tax_code_name'] = $tax_code_info->tax_code_name;
			$data['tax_code_type'] = $tax_code_info->tax_code_type;
			$data['city'] = $tax_code_info->city;
			$data['state'] = $tax_code_info->state;
			$data['rate_tax_code'] = $tax_code_info->rate_tax_code;
			$data['rate_tax_category_id'] = $tax_code_info->rate_tax_category_id;
			$data['tax_category'] = $tax_code_info->tax_category;
			$data['add_tax_category'] = '';
			$data['tax_rate'] = $tax_rate_info->tax_rate;
			$data['rounding_code'] = $tax_rate_info->rounding_code;
		}

		$data = $this->xss_clean($data);

		$tax_rates = [];
		foreach($this->tax->get_tax_code_rate_exceptions($tax_code) as $tax_code_rate)	//TODO: get_tax_code_rate_exceptions doesn't exist
		{
			$tax_rate_row = [];
			$tax_rate_row['rate_tax_category_id'] = $this->xss_clean($tax_code_rate['rate_tax_category_id']);
			$tax_rate_row['tax_category'] = $this->xss_clean($tax_code_rate['tax_category']);
			$tax_rate_row['tax_rate'] = $this->xss_clean($tax_code_rate['tax_rate']);
			$tax_rate_row['rounding_code'] = $this->xss_clean($tax_code_rate['rounding_code']);

			$tax_rates[] = $tax_rate_row;
		}

		$data['tax_rates'] = $tax_rates;

		echo view('taxes/tax_code_form', $data);
	}


	public function view(int $tax_rate_id = -1): void	//TODO: Replace -1 with constant
	{
		$tax_rate_info = $this->tax->get_info($tax_rate_id);

		$data['tax_rate_id'] = $tax_rate_id;
		$data['rounding_options'] = rounding_mode::get_rounding_options();

		$data['tax_code_options'] = $this->tax_lib->get_tax_code_options();
		$data['tax_category_options'] = $this->tax_lib->get_tax_category_options();
		$data['tax_jurisdiction_options'] = $this->tax_lib->get_tax_jurisdiction_options();

		if($tax_rate_id == -1)	//TODO: Replace -1 with constant
		{
			$data['rate_tax_code_id'] = $this->appconfig->get('default_tax_code');
			$data['rate_tax_category_id'] = $this->appconfig->get('default_tax_category');
			$data['rate_jurisdiction_id'] = $this->appconfig->get('default_tax_jurisdiction');
			$data['tax_rounding_code'] = rounding_mode::HALF_UP;
			$data['tax_rate'] = '0.0000';
		}
		else
		{
			$data['rate_tax_code_id'] = $tax_rate_info->rate_tax_code_id;
			$data['rate_tax_code'] = $tax_rate_info->tax_code;
			$data['rate_tax_category_id'] = $tax_rate_info->rate_tax_category_id;
			$data['rate_jurisdiction_id'] = $tax_rate_info->rate_jurisdiction_id;
			$data['tax_rounding_code'] = $tax_rate_info->tax_rounding_code;
			$data['tax_rate'] = $tax_rate_info->tax_rate;
		}

		$data = $this->xss_clean($data);

		echo view('taxes/tax_rates_form', $data);
	}



	public function view_tax_categories(int $tax_code = -1): void	//TODO: Replace -1 with constant
	{
		$tax_code_info = $this->tax->get_info($tax_code);	//TODO: Duplicated Code

		$default_tax_category_id = 1; // Tax category id is always the default tax category
		$default_tax_category = $this->tax->get_tax_category($default_tax_category_id);

		$tax_rate_info = $this->tax->get_rate_info($tax_code, $default_tax_category_id);

		$data['rounding_options'] = rounding_mode::get_rounding_options();
		$data['html_rounding_options'] = $this->get_html_rounding_options();

		if($this->appconfig->get('tax_included') == '1')	//TODO: this should be replaced with === since appconfig value is always going to be a string
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		if($tax_code == -1)	//TODO: Replace -1 with constant
		{
			$data['tax_code'] = '';
			$data['tax_code_name'] = '';
			$data['tax_code_type'] = '0';
			$data['city'] = '';
			$data['state'] = '';
			$data['tax_rate'] = '0.0000';
			$data['rate_tax_code'] = '';
			$data['rate_tax_category_id'] = 1;
			$data['tax_category'] = '';
			$data['add_tax_category'] = '';
			$data['rounding_code'] = '0';
		}
		else
		{
			$data['tax_code'] = $tax_code;
			$data['tax_code_name'] = $tax_code_info->tax_code_name;
			$data['tax_code_type'] = $tax_code_info->tax_code_type;
			$data['city'] = $tax_code_info->city;
			$data['state'] = $tax_code_info->state;
			$data['rate_tax_code'] = $tax_code_info->rate_tax_code;
			$data['rate_tax_category_id'] = $tax_code_info->rate_tax_category_id;
			$data['tax_category'] = $tax_code_info->tax_category;
			$data['add_tax_category'] = '';
			$data['tax_rate'] = $tax_rate_info->tax_rate;
			$data['rounding_code'] = $tax_rate_info->rounding_code;
		}

		$data = $this->xss_clean($data);

		$tax_rates = [];
		foreach($this->tax->get_tax_code_rate_exceptions($tax_code) as $tax_code_rate)	//TODO: get_tax_code_rate_exceptions doesn't exist in the tax model
		{
			$tax_rate_row = [];
			$tax_rate_row['rate_tax_category_id'] = $this->xss_clean($tax_code_rate['rate_tax_category_id']);
			$tax_rate_row['tax_category'] = $this->xss_clean($tax_code_rate['tax_category']);
			$tax_rate_row['tax_rate'] = $this->xss_clean($tax_code_rate['tax_rate']);
			$tax_rate_row['rounding_code'] = $this->xss_clean($tax_code_rate['rounding_code']);

			$tax_rates[] = $tax_rate_row;
		}

		$data['tax_rates'] = $tax_rates;

		echo view('taxes/tax_category_form', $data);
	}

	public function view_tax_jurisdictions(int $tax_code = -1): void	//TODO: Replace -1 with constant
	{
		$tax_code_info = $this->tax->get_info($tax_code);	//TODO: Duplicated code

		$default_tax_category_id = 1; // Tax category id is always the default tax category
		$default_tax_category = $this->tax->get_tax_category($default_tax_category_id);

		$tax_rate_info = $this->tax->get_rate_info($tax_code, $default_tax_category_id);

		$data['rounding_options'] = rounding_mode::get_rounding_options();
		$data['html_rounding_options'] = $this->get_html_rounding_options();

		if($this->appconfig->get('tax_included') == '1')	//TODO: this should be replaced with === since appconfig value is always going to be a string
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		if($tax_code == -1)	//TODO: Replace -1 with constant
		{
			$data['tax_code'] = '';
			$data['tax_code_name'] = '';
			$data['tax_code_type'] = '0';
			$data['city'] = '';
			$data['state'] = '';
			$data['tax_rate'] = '0.0000';
			$data['rate_tax_code'] = '';
			$data['rate_tax_category_id'] = 1;
			$data['tax_category'] = '';
			$data['add_tax_category'] = '';
			$data['rounding_code'] = '0';
		}
		else
		{
			$data['tax_code'] = $tax_code;
			$data['tax_code_name'] = $tax_code_info->tax_code_name;
			$data['tax_code_type'] = $tax_code_info->tax_code_type;
			$data['city'] = $tax_code_info->city;
			$data['state'] = $tax_code_info->state;
			$data['rate_tax_code'] = $tax_code_info->rate_tax_code;
			$data['rate_tax_category_id'] = $tax_code_info->rate_tax_category_id;
			$data['tax_category'] = $tax_code_info->tax_category;
			$data['add_tax_category'] = '';
			$data['tax_rate'] = $tax_rate_info->tax_rate;
			$data['rounding_code'] = $tax_rate_info->rounding_code;
		}

		$data = $this->xss_clean($data);

		$tax_rates = [];
		foreach($this->tax->get_tax_code_rate_exceptions($tax_code) as $tax_code_rate)	//TODO: get_tax_code_rate_exceptions doesn't exist in the tax model
		{
			$tax_rate_row = [];
			$tax_rate_row['rate_tax_category_id'] = $this->xss_clean($tax_code_rate['rate_tax_category_id']);
			$tax_rate_row['tax_category'] = $this->xss_clean($tax_code_rate['tax_category']);
			$tax_rate_row['tax_rate'] = $this->xss_clean($tax_code_rate['tax_rate']);
			$tax_rate_row['rounding_code'] = $this->xss_clean($tax_code_rate['rounding_code']);

			$tax_rates[] = $tax_rate_row;
		}

		$data['tax_rates'] = $tax_rates;

		echo view('taxes/tax_jurisdiction_form', $data);
	}

	public static function get_html_rounding_options(): string
	{
		return rounding_mode::get_html_rounding_options();
	}

	public function save(int $tax_rate_id = -1): void	//TODO: Replace -1 with constant
	{
		$tax_category_id = $this->request->getPost('rate_tax_category_id');
		$tax_rate = parse_tax($this->request->getPost('tax_rate'));

		if ($tax_rate == 0)	//TODO: Replace 0 with constant?
		{
			$tax_category_info = $this->tax_category->get_info($tax_category_id);
		}

		$tax_rate_data = [
			'rate_tax_code_id' => $this->request->getPost('rate_tax_code_id'),
			'rate_tax_category_id' => $this->request->getPost('rate_tax_category_id'),
			'rate_jurisdiction_id' => $this->request->getPost('rate_jurisdiction_id'),
			'tax_rate' => $tax_rate,
			'tax_rounding_code' => $this->request->getPost('tax_rounding_code')
		];

		if($this->tax->save($tax_rate_data, $tax_rate_id))
		{
			if($tax_rate_id == -1)	//TODO: Replace -1 with constant
			{//TODO: this needs to be replaced with ternary notation
				echo json_encode (['success' => TRUE, 'message' => lang('Taxes.tax_rate_successfully_added')]);
			}
			else //Existing tax_code
			{
				echo json_encode (['success' => TRUE, 'message' => lang('Taxes.tax_rate_successful_updated')]);
			}
		}
		else
		{
			echo json_encode (['success' => FALSE, 'message' => lang('Taxes.tax_rate_error_adding_updating')]);
		}
	}

	public function delete(): void
	{
		$tax_codes_to_delete = $this->xss_clean($this->request->getPost('ids'));

		if($this->tax->delete_list($tax_codes_to_delete))	//TODO: this needs to be replaced with ternary notation
		{
			echo json_encode (['success' => TRUE, 'message' => lang('Taxes.tax_code_successful_deleted')]);
		} else
		{
			echo json_encode (['success' => FALSE, 'message' => lang('Taxes.tax_code_cannot_be_deleted')]);
		}
	}

	public function suggest_tax_codes(): void
	{
		$suggestions = $this->xss_clean($this->tax_code->get_tax_codes_search_suggestions($this->request->getPostGet('term')));

		echo json_encode($suggestions);
	}

	public function save_tax_codes(): void
	{
		$tax_code_id = $this->request->getPost('tax_code_id');
		$tax_code = $this->request->getPost('tax_code');
		$tax_code_name = $this->request->getPost('tax_code_name');
		$tax_code_id = $this->request->getPost('tax_code_id');
		$city = $this->request->getPost('city');
		$state = $this->request->getPost('state');

		$array_save = [];	//TODO: the naming of this variable is not good.
		foreach($tax_code_id as $key=>$val)
		{
			$array_save[] = [
				'tax_code_id'=>$this->xss_clean($val),
				'tax_code'=>$this->xss_clean($tax_code[$key]),
				'tax_code_name'=>$this->xss_clean($tax_code_name[$key]),
				'city'=>$this->xss_clean($city[$key]),
				'state'=>$this->xss_clean($state[$key])
			];
		}

		$success = $this->tax_code->save_tax_codes($array_save);

		echo json_encode ([
			'success' => $success,
			'message' => lang('Taxes.tax_codes_saved_' . ($success ? '' : 'un') . 'successfully')
		]);
	}

	public function save_tax_jurisdictions(): void
	{
		$jurisdiction_id = $this->request->getPost('jurisdiction_id');
		$jurisdiction_name = $this->request->getPost('jurisdiction_name');
		$tax_group = $this->request->getPost('tax_group');
		$tax_type = $this->request->getPost('tax_type');
		$reporting_authority = $this->request->getPost('reporting_authority');
		$tax_group_sequence = $this->request->getPost('tax_group_sequence');
		$cascade_sequence = $this->request->getPost('cascade_sequence');

		$array_save = [];
		$unique_tax_groups = [];

		foreach($jurisdiction_id as $key => $val)
		{
			$array_save[] = [
				'jurisdiction_id'=>$this->xss_clean($val),
				'jurisdiction_name'=>$this->xss_clean($jurisdiction_name[$key]),
				'tax_group'=>$this->xss_clean($tax_group[$key]),
				'tax_type'=>$this->xss_clean($tax_type[$key]),
				'reporting_authority'=>$this->xss_clean($reporting_authority[$key]),
				'tax_group_sequence'=>$this->xss_clean($tax_group_sequence[$key]),
				'cascade_sequence'=>$this->xss_clean($cascade_sequence[$key])
			];

			if (array_search($tax_group[$key], $unique_tax_groups) !== false)
			{
				echo json_encode ([
					'success' => FALSE,
					'message' => lang('Taxes.tax_group_not_unique', $tax_group[$key])
				]);
				return;
			}
			else
			{
				$unique_tax_groups[] = $tax_group[$key];
			}
		}

		$success = $this->tax_jurisdiction->save_jurisdictions($array_save);

		echo json_encode ([
			'success' => $success,
			'message' => lang('Taxes.tax_jurisdictions_saved_' . ($success ? '' : 'un') . 'successfully')
		]);
	}

	public function save_tax_categories(): void
	{
		$tax_category_id = $this->request->getPost('tax_category_id');
		$tax_category = $this->request->getPost('tax_category');
		$tax_group_sequence = $this->request->getPost('tax_group_sequence');

		$array_save= [];

		foreach($tax_category_id as $key => $val)
		{
			$array_save[] = [
				'tax_category_id'=>$this->xss_clean($val),
				'tax_category'=>$this->xss_clean($tax_category[$key]),
				'tax_group_sequence'=>$this->xss_clean($tax_group_sequence[$key])
			];
		}

		$success = $this->tax_category->save_categories($array_save);

		echo json_encode ([
			'success' => $success,
			'message' => lang('Taxes.tax_categories_saved_' . ($success ? '' : 'un') . 'successfully')
		]);
	}

	public function ajax_tax_codes(): void
	{
		$tax_codes = $this->tax_code->get_all()->getResultArray();

		$tax_codes = $this->xss_clean($tax_codes);

		echo view('partial/tax_codes', ['tax_codes' => $tax_codes]);
	}

	public function ajax_tax_categories(): void
	{
		$tax_categories = $this->tax_category->get_all()->getResultArray();

		$tax_categories = $this->xss_clean($tax_categories);

		echo view('partial/tax_categories', ['tax_categories' => $tax_categories]);
	}

	public function ajax_tax_jurisdictions(): void
	{
		$tax_jurisdictions = $this->tax_jurisdiction->get_all()->getResultArray();

		if($this->appconfig->get('tax_included') == '1')
		{
			$default_tax_type = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$default_tax_type = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		$tax_jurisdictions = $this->xss_clean($tax_jurisdictions);
		$tax_types = $this->tax_lib->get_tax_types();

		echo view('partial/tax_jurisdictions', [
			'tax_jurisdictions' => $tax_jurisdictions,
			'tax_types' => $tax_types,
			'default_tax_type' => $default_tax_type
		]);
	}
}
?>