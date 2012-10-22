<?php
class ExcelDocument_Worksheet
{
	private $xls = null;
	private $factory = null;
	private $current_row = 0;

	function __construct(ExcelDocument $document, $name = null, $data = array())
	{
		$this->xls = $document;
		if ($name === null) { $name = "Worksheet"; }
		$this->factory = new PHPExcel_Worksheet($this->xls->factory(), $name);
		$this->xls->factory()->addSheet($this->factory);
		if (count($data)) { $this->data($data); }
	}

	function document() { return $this->xls; }
	function factory() { return $this->factory; }

	// Добавляет новую строку в конец списка
	function append()
	{
		$args = func_get_args();
		if (count($args) == 1 && is_array($args[0])) { $row = $args[0]; } else { $row = $args; }
		$cell_index = 0;
		foreach ($row as $cell)
		{
			$this->cell($cell_index, $this->current_row, $cell);
			$cell_index++;
		}
		$this->current_row++;
	}

	// Добавление нескольких строк разом
	function data($data) { foreach ($data as $row) $this->append($row); }

	// Установка или получение названия книги
	function name()
	{
		if (count(func_get_args()) > 0) { return $this->factory->setTitle(func_get_arg(0)); }
		else { return $this->factory->getTitle(); }
	}

	// Доступ к конкретной строке по её номеру (нумерация с 0)
	function row($index = 0) { return new ExcelDocument_Row($this, $index); }

	// Доступ к конкретному стролбцу по его номеру (нумерация с 0)
	function column($index = 0) { return new ExcelDocument_Column($this, $index); }

	// Замораживает строку и/или столбец, нумерация с 0, но чтобы заморозить нужно
	// указывать тот номер столбца или строки выше которого всё должно быть заморожено,
	// сам же столбец или строка заморожены не будут, если указать 0 - заморозки также не будет,
	// т.к. строк или столбца выше - нет
	// Заморозка создаёт эффект зависания строк или столбцов, когда скролляться все остальные кроме них
	function freeze($row_index = 0, $column_index = 0)
	{
		if ($row_index == 0 && $column_index == 0) { $this->factory->unfreezePane(); }
		else { $this->factory->freezePaneByColumnAndRow($column_index, $row_index + 1); }
	}

	// Установка значения конкретной ячейки по её номеру столбца и строки (нумерация с 0)
	function cell($column_index = 0, $row_index = 0, $cell)
	{
		if (!$cell instanceof ExcelDocument_Cell) { $cell = new ExcelDocument_Cell($this->xls, $cell); }

		$value = $cell->value(); $style = $cell->style(); $type = $cell->type();

		if ($cell->type()) { $this->factory->setCellValueExplicitByColumnAndRow($column_index, $row_index + 1, $cell->value(), $cell->type()); }
		else { $this->factory->setCellValueByColumnAndRow($column_index, $row_index + 1, $cell->value()); }

		$this->factory->getStyleByColumnAndRow($column_index, $row_index + 1)->applyFromArray($cell->style()->settings());

		if ($cell->rowspan() > 1 || $cell->colspan() > 1)
		{ $this->factory->mergeCellsByColumnAndRow($column_index, $row_index + 1, $column_index + $cell->colspan() - 1, ($row_index + 1) + ($cell->rowspan() - 1)); }

		return $cell;
	}

	// Видимость листа
	// true, 1 - невидим, но пользователь может показать
	// 2 - невидим совсем, пользователь не сможет увидеть никак
	// false, 0  - видимый
	function hidden($hidden = false)
	{
		if ($hidden == 2) { $this->factory->setSheetState(PHPExcel_Worksheet::SHEETSTATE_VERYHIDDEN); }
		elseif ($hidden) { $this->factory->setSheetState(PHPExcel_Worksheet::SHEETSTATE_HIDDEN); }
		else { $this->factory->setSheetState(PHPExcel_Worksheet::SHEETSTATE_VISIBLE); }
	}

	// Возвращает индекс самой последней строки
	function last_row() { return ($this->factory->getHighestRow() - 1); }

	// Возвращает индекс самой последней колонки
	function last_column() { return (PHPExcel_Cell::columnIndexFromString($this->factory->getHighestColumn()) - 1); }

	// Показывать или нет линии таблицы
    function show_grid()
    {
		if (count(func_get_args()) > 0) { return $this->factory->setShowGridlines(func_get_arg(0)); }
		else { return $this->factory->getShowGridlines(); }
    }

	// Печатать или нет линии таблицы
    function print_grid()
    {
		if (count(func_get_args()) > 0) { return $this->factory->setPrintGridlines(func_get_arg(0)); }
		else { return $this->factory->getPrintGridlines(); }
    }
}