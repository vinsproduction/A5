<?php
class ExcelDocument_Row
{
	private $sheet = null;
	private $index = 0;

	function __construct(ExcelDocument_Worksheet $sheet, $index)
	{
		$this->sheet = $sheet;
		$this->index = $index;
	}

	function dimension() { return $this->sheet->factory()->getRowDimension($this->index + 1); }

	// Возвращает строковое наименование столбца Excel
	function name() { return ($this->index + 1); }

	function height()
	{
		if (count(func_get_args()) > 0) { return $this->dimension()->setRowHeight(func_get_arg(0)); }
		else { return $this->dimension()->getRowHeight(); }
	}

	function visible()
	{
		if (count(func_get_args()) > 0) { return $this->dimension()->setVisible(func_get_arg(0)); }
		else { return $this->dimension()->getVisible(); }
	}

	function outline_level()
	{
		if (count(func_get_args()) > 0) { return $this->dimension()->setOutlineLevel(func_get_arg(0)); }
		else { return $this->dimension()->getOutlineLevel(); }
	}

	function collapsed()
	{
		if (count(func_get_args()) > 0) { return $this->dimension()->setCollapsed(func_get_arg(0)); }
		else { return $this->dimension()->getCollapsed(); }
	}

	// Указывает что после данной строки должен быть перенос на новую страницу (для печати)
	public function set_break($is_break = true)
	{ $this->sheet->factory()->setBreakByColumnAndRow(0, $this->index + 1, $is_break ? PHPExcel_Worksheet::BREAK_ROW : PHPExcel_Worksheet::BREAK_NONE); }
}