<?php
class ExcelDocument_Cell
{
	private $xls = null;
	private $value = null;
	private $type = null;
	private $colspan = 1;
	private $rowspan = 1;
	private $style = null;

	function __construct(ExcelDocument $document, $value, $style = null)
	{
		$this->xls = $document;
		$this->value = $value;

		if ($style)
		{
			$style_object = new ExcelDocument_Style($this->xls->get_default_cell_style());
			$style_object->merge($style);
			$this->style = $style_object;
		}
		else { $this->style = new ExcelDocument_Style($this->xls->get_default_cell_style()); }

		if (preg_match("/(^\s*|\s*;\s*)colspan:\s*(\d+)\s*($|;)/sxu", $style, $regs)) { $this->colspan = $regs[2] > 1 ? $regs[2] : 1; }
		if (preg_match("/(^\s*|\s*;\s*)rowspan:\s*(\d+)\s*($|;)/sxu", $style, $regs)) { $this->rowspan = $regs[2] > 1 ? $regs[2] : 1; }

		if (preg_match("/(^\s*|\s*;\s*)type:\s*(string|formula|numeric|bool|null|inline|error|date|datetime|time|currency|currency-int|money|money-int)\s*($|;)/sxu", $style, $regs))
		{ $user_type = $regs[2]; } else { $user_type = null; }

		// Если тип данных не указали, пытаемся определить его автоматически
		if ($user_type === null)
		{
			if (is_empty($this->value)) { $user_type = "null"; }
			elseif (is_numeric($this->value)) { $user_type = "numeric"; }
			elseif (Valid::date($this->value)) { $user_type = "date"; }
		}

		// Если указали тип или его удалось определить - конвертируем данные некоторых типов
		if ($user_type !== null)
		{
			// Если данные - пустые - объявляем тип как "null"
			if (is_empty($this->value)) { $user_type = "null"; }

			// Получаем текущие настройки стиля
			$settings = $this->style->settings();

			switch ($user_type)
			{
				case "date":
				case "datetime":
				case "time":
					if (Valid::date($this->value))
					{
						$date = Date::format($this->value);
						$this->value = PHPExcel_Shared_Date::FormattedPHPToExcel($date->format("Y"), $date->format("m"), $date->format("d"), $date->format("H"), $date->format("i"), $date->format("s"));
					}
					break;

				case "currency":
				case "currency-int":
					$number_format = ($user_type == "currency-int") ? "##,#0" : "##,#0.00";
					// Если это денежные единицы в формате (4995.12 $)
					if (preg_match("/^ ( \d+ (?: \.\d+)? ) (\s*.+) $/sxu", $this->value, $regs))
					{
						$this->value = $regs[1];
						if (!isset($settings["numberformat"]["code"])) { $settings["numberformat"]["code"] = $number_format . "[$" . $regs[2] . "]"; }
					}
					// Если это денежные единицы в формате ($ 4995.12)
					elseif (preg_match("/^ (.+?\s*?) ( \d+ (?: \.\d+)? ) $/sxu", $this->value, $regs))
					{
						$this->value = $regs[2];
						if (!isset($settings["numberformat"]["code"])) { $settings["numberformat"]["code"] = "[$" . $regs[1] . "]" . $number_format; }
					}
					break;
			}

			switch ($user_type)
			{
				case 'string': $this->type = PHPExcel_Cell_DataType::TYPE_STRING; break;
				case 'formula': $this->type = PHPExcel_Cell_DataType::TYPE_FORMULA; break;

				case 'numeric':
				case 'date':
				case 'datetime':
				case 'time':
				case 'currency':
				case 'money':
					$this->type = PHPExcel_Cell_DataType::TYPE_NUMERIC; break;

				case 'bool': $this->type = PHPExcel_Cell_DataType::TYPE_BOOL; break;
				case 'null': $this->type = PHPExcel_Cell_DataType::TYPE_NULL; break;
				case 'inline': $this->type = PHPExcel_Cell_DataType::TYPE_INLINE; break;
				case 'error': $this->type = PHPExcel_Cell_DataType::TYPE_ERROR; break;
			}

			// Если не указано форматирование - указываем его для некоторых типов
			if (!isset($settings["numberformat"]["code"]))
			{
				switch ($user_type)
				{
					case 'date': $settings["numberformat"]["code"] = "dd.mm.yyyy"; break;
					case 'datetime': $settings["numberformat"]["code"] = "dd.mm.yyyy h:mm:ss"; break;
					case 'time': $settings["numberformat"]["code"] = "h:mm:ss"; break;
					case 'money': $settings["numberformat"]["code"] = "##,#0.00"; break;
					case 'money-int': $settings["numberformat"]["code"] = "##,#0"; break;
				}
			}

			// Стиль мог быть подифицирован
			$this->style->merge($settings);
		}
	}

	function value() { return $this->value; }
	function style() { return $this->style; }
	function type() { return $this->type; }
	function colspan() { return $this->colspan; }
	function rowspan() { return $this->rowspan; }
}