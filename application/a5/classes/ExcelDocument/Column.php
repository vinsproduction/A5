<?php
class ExcelDocument_Column
{
	private $sheet = null;
	private $index = 0;

	function __construct(ExcelDocument_Worksheet $sheet, $index)
	{
		$this->sheet = $sheet;
		$this->index = $index;
	}

	function dimension() { return $this->sheet->factory()->getColumnDimensionByColumn($this->index); }

	// Возвращает строковое наименование столбца Excel
	function name() { return PHPExcel_Cell::stringFromColumnIndex($this->index); }

	function width()
	{
		if (count(func_get_args()) > 0) { return $this->dimension()->setWidth(func_get_arg(0)); }
		else { return $this->dimension()->getWidth(); }
	}

	function auto_size()
	{
		if (count(func_get_args()) > 0) { return $this->dimension()->setAutoSize(func_get_arg(0)); }
		else { return $this->dimension()->getAutoSize(); }
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

	// Указывает что после данного столбца должен быть перенос на следующую страницу
	public function set_break($is_break = true)
	{ $this->sheet->factory()->setBreakByColumnAndRow($this->index + 1, 1, $is_break ? PHPExcel_Worksheet::BREAK_COLUMN : PHPExcel_Worksheet::BREAK_NONE); }
}