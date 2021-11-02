<?php

namespace App\Controllers;

use app\Models\Expense_category;

/**
 *
 *
 * @property expense_category expense_category
 *
 */
class Expenses_categories extends Secure_Controller	//TODO: Is this class ever used?
{
	public function __construct()
	{
		parent::__construct('expenses_categories');

		$this->expense_category = model('Expense_category');
	}

	public function index(): void
	{
		 $data['table_headers'] = $this->xss_clean(get_expense_category_manage_table_headers());

		 echo view('expenses_categories/manage', $data);
	}

	/*
	Returns expense_category_manage table data rows. This will be called with AJAX.
	*/
	public function search(): void
	{
		$search = $this->request->getGet('search');
		$limit  = $this->request->getGet('limit');
		$offset = $this->request->getGet('offset');
		$sort   = $this->request->getGet('sort');
		$order  = $this->request->getGet('order');

		$expense_categories = $this->expense_category->search($search, $limit, $offset, $sort, $order);
		$total_rows = $this->expense_category->get_found_rows($search);

		$data_rows = [];
		foreach($expense_categories->getResult() as $expense_category)
		{
			$data_rows[] = $this->xss_clean(get_expense_category_data_row($expense_category));
		}

		echo json_encode (['total' => $total_rows, 'rows' => $data_rows]);
	}

	public function get_row(int $row_id): void
	{
		$data_row = $this->xss_clean(get_expense_category_data_row($this->expense_category->get_info($row_id)));

		echo json_encode($data_row);
	}

	public function view(int $expense_category_id = -1): void	//TODO: Replace -1 with a constant
	{
		$data['category_info'] = $this->expense_category->get_info($expense_category_id);

		echo view("expenses_categories/form", $data);
	}

	public function save(int $expense_category_id = -1): void	//TODO: Replace -1 with a constant
	{
		$expense_category_data = [
			'category_name' => $this->request->getPost('category_name'),
			'category_description' => $this->request->getPost('category_description')
		];

		if($this->expense_category->save($expense_category_data, $expense_category_id))	//TODO: Reflection exception
		{
			$expense_category_data = $this->xss_clean($expense_category_data);

			// New expense_category_id
			if($expense_category_id == -1)
			{
				echo json_encode ([
					'success' => TRUE,
					'message' => lang('Expenses_categories.successful_adding'),
					'id' => $expense_category_data['expense_category_id']
				]);
			}
			else // Existing Expense Category
			{
				echo json_encode ([
					'success' => TRUE,
					'message' => lang('Expenses_categories.successful_updating'),
					'id' => $expense_category_id
				]);
			}
		}
		else//failure
		{//TODO: need to replace -1 for a constant
			echo json_encode ([
				'success' => FALSE,
				'message' => lang('Expenses_categories.error_adding_updating') . ' ' . $expense_category_data['category_name'],
				'id' => -1
			]);
		}
	}

	public function delete(): void
	{
		$expense_category_to_delete = $this->request->getPost('ids');

		if($this->expense_category->delete_list($expense_category_to_delete))	//TODO: Convert to ternary notation.
		{
			echo json_encode([
				'success' => TRUE,
				'message' => lang('Expenses_categories.successful_deleted') . ' ' . count($expense_category_to_delete) . ' ' . lang('Expenses_categories.one_or_multiple')
			]);
		}
		else
		{
			echo json_encode (['success' => FALSE, 'message' => lang('Expenses_categories.cannot_be_deleted')]);
		}
	}
}
?>