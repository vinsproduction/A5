<?php
class ExcelDocument_Reader
{
	private $xls = null;
	private $sheet = null;
	private $row_index = null;
	private $error = false;

	function load($filename)
	{
		if (!file_exists($filename)) { $this->error("No such file or directory"); }
		elseif (!is_readable($filename)) { $this->error("File is not readable"); }

		if (!$this->error())
		{
			$extension = get_file_extension($filename);
			$extension = ".xls";

			// Приоритет перебора типов файлов
			$classes = array
			(
				'Excel5',
				'Excel2007',
				'Excel2003XML',
				'OOCalc',
			);

			switch (strtolower($extension))
			{
				case '.xls': $reader_name = 'Excel5'; break;
				case '.xlsx': $reader_name = 'Excel2007'; break;
				case '.xml': $reader_name = 'Excel2003XML'; break;
				case '.ods': $reader_name = 'OOCalc'; break;
			}

			if (isset($reader_name))
			{
				$key = array_search($reader_name, $classes);
				if ($key !== false) { unset($classes[$key]); }
				array_unshift($classes, $reader_name);
			}

			foreach ($classes as $class)
			{
				$reader = PHPExcel_IOFactory::createReader($class);
				if ($reader->canRead($filename))
				{
//					try
//					{
						$this->xls = $reader->load($filename);
						$this->sheet = $this->xls->getSheet(0);
						return $this->xls;
//					}
//					catch (Exception $e)
//					{ $this->error($e->getMessage()); break; }
				}
			}

			if (!$this->error()) { $this->error("Unknown file format"); }
		}

		return false;
	}

	function error()
	{
		$args = func_get_args();
		if (count($args))
		{
			$this->error = $args[0];
			throw_error($this->error);
		}
		return $this->error;
	}

	function sheets()
	{
		$sheets = $this->xls->getAllSheets();
		$sheet_names = array();
		foreach ($sheets as $i => $sheet) { $sheet_names[$i] = $sheet->getTitle(); }
		return $sheet_names;
	}

	function sheet($index)
	{
		if (is_numeric($index)) { $this->sheet = $this->xls->getSheet($index); }
		else { $this->sheet = $this->xls->getSheetByName($index); }
		$this->reset();
		return $this->sheet;
	}

	function reset() { $this->row_index = null; }

	// Вы можете передать массив с именами полей, они будут использоваться в качестве ключей
	// возвращаемого массива взамен простой нумерации по порядку
	function next($field_names = array())
	{
		$rows_count = $this->sheet->getHighestRow();
		if ($this->row_index === null) { $this->row_index = 1; }
		while ($this->row_index <= $rows_count)
		{
			$line = array_flip($field_names);
			array_walk($line, function(&$v) { $v = null; });
			$cols_count = PHPExcel_Cell::columnIndexFromString($this->sheet->getHighestColumn());
			for ($col_index = 0; $col_index <= $cols_count; $col_index++)
			{
				$cell = $this->sheet->getCellByColumnAndRow($col_index, $this->row_index);
				$value = trim($cell->getCalculatedValue());
				if (isset($field_names[$col_index])) { $line[$field_names[$col_index]] = $value; } else { $line[$col_index] = $value; }
			}
			$this->row_index++;
			return $line;
		}
		return false;
	}
}