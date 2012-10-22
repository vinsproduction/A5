<?php
class ExcelDocument_Style
{
	private $settings = array();

	function __construct($style = null) { $this->apply($style); }

	function apply($style)
	{
		$this->settings = array();
		$this->merge($style);
	}

	// Добавляет к текущему стилю какие-либо параметры
	function merge($style)
	{
		if (!is_array($style))
		{
			// font-family" Arial
			if (preg_match("/(^\s*|\s*;\s*)font-family:\s*([^;]+)\s*($|;)/sxu", $style, $regs)) { $this->settings["font"]["name"] = $regs[2]; }
			// font-size: 12px
			if (preg_match("/(^\s*|\s*;\s*)font-size:\s*(\d+)([a-zA-Z]+)?\s*($|;)/sxu", $style, $regs)) { $this->settings["font"]["size"] = $regs[2]; }
			// color: #00ff00
			if (preg_match("/(^\s*|\s*;\s*)color:\s*(\#[a-f0-9]+)\s*($|;)/sxu", $style, $regs)) { $this->settings["font"]["color"]["rgb"] = str_replace("#", "", $regs[2]); }
			// font-weight: bold, font-weight: normal
			if (preg_match("/(^\s*|\s*;\s*)font-weight:\s*(bold|normal)\s*($|;)/sxu", $style, $regs)) { $this->settings["font"]["bold"] = ($regs[2] == "bold" ? true : false); }

			// text-decoration: none,underline,strike,double-underline
			if (preg_match("/(^\s*|\s*;\s*)text-decoration:\s*(none|underline|strike|double-underline)\s*($|;)/sxu", $style, $regs))
			{
				if ($regs[2] == "underline") { $this->settings["font"]["underline"] = PHPExcel_Style_Font::UNDERLINE_SINGLE; }
				elseif ($regs[2] == "double-underline") { $this->settings["font"]["underline"] = PHPExcel_Style_Font::UNDERLINE_DOUBLE; }
				else { $this->settings["font"]["underline"] = PHPExcel_Style_Font::UNDERLINE_NONE; }
				if ($regs[2] == "strike") { $this->settings["font"]["strike"] = true; } else { $this->settings["font"]["strike"] = false; }
				$this->settings["font"]["italic"] = ($regs[2] == "italic" ? true : false);
			}

			// font-style: italic, normal
			if (preg_match("/(^\s*|\s*;\s*)font-style:\s*(italic|normal)\s*($|;)/sxu", $style, $regs)) { $this->settings["font"]["italic"] = ($regs[2] == "italic" ? true : false); }

			// border: solid #00ff00, border: double, border: none
			// толщины в пикселях нет!!
			foreach (array("border", "border-top", "border-bottom", "border-left", "border-right") as $param)
			{
				if (preg_match("/(^\s*|\s*;\s*)$param:\s*([^;]+)\s*($|;)/sxu", $style, $regs))
				{
					$border = $regs[2];
					$border_color = "#000000";
					$border_style = "none";

					if (preg_match("/\s*(none|dashed|dotted|double|thick|solid|thin|hair)\s*/sxu", $border, $regs)) { $border_style = $regs[1]; }
					if (preg_match("/\s*(\#[a-f0-9]+)\s*/sxu", $border, $regs)) { $border_color = $regs[1]; }

					if ($param == "border") { $prefixes = array("top", "bottom", "left", "right"); }
					if ($param == "border-top") { $prefixes = array("top"); }
					if ($param == "border-bottom") { $prefixes = array("bottom"); }
					if ($param == "border-right") { $prefixes = array("right"); }
					if ($param == "border-left") { $prefixes = array("left"); }

					foreach ($prefixes as $prefix)
					{
						$this->settings["borders"][$prefix]["color"]["rgb"] = str_replace("#", "", $border_color);
						switch ($border_style)
						{
							case "none": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_NONE; break;
							// Через тире
							case "dashed": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_DASHED; break;
							// Крупная точка
							case "dotted": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_DOTTED; break;
							// Двойной
							case "double": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_DOUBLE; break;
							// Жирный
							case "thick": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_THICK; break;
							// Средний
							case "solid": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_MEDIUM; break;
							// Тонкий
							case "thin": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_THIN; break;
							// Очень мелкая точка
							case "hair": $this->settings["borders"][$prefix]["style"] = PHPExcel_Style_Border::BORDER_HAIR; break;
						}
					}
				}
			}

			// background: #00ff00, background-color: #00ff00
			if (preg_match("/(^\s*|\s*;\s*)background(?:-color)?:\s*(\#[a-f0-9]+)\s*($|;)/sxu", $style, $regs))
			{
				$this->settings["fill"]["type"] = PHPExcel_Style_Fill::FILL_SOLID;
				$this->settings["fill"]["color"]["rgb"] = str_replace("#", "", $regs[2]);
			}

			// text-indent: 5
			if (preg_match("/(^\s*|\s*;\s*)text-indent:\s*([+-]?\d+)([a-zA-Z]+)?\s*($|;)/sxu", $style, $regs)) { $this->settings["alignment"]["indent"] = $regs[2]; }
 			// text-wrap: wrap,shrink
			if (preg_match("/(^\s*|\s*;\s*)text-wrap:\s*(wrap|shrink)\s*($|;)/sxu", $style, $regs))
			{
				switch ($regs[2])
				{
					case "wrap": $this->settings["alignment"]["wrap"] = true; break;
					case "shrink": $this->settings["alignment"]["shrinkToFit"] = true; break;
				}
			}
			// text-rotation: 90, text-rotation: -45
			if (preg_match("/(^\s*|\s*;\s*)text-rotation:\s*([+-]?\d+)\s*($|;)/sxu", $style, $regs)) { $this->settings["alignment"]["rotation"] = $regs[2]; }
			// text-align: center,left,right,justify
			if (preg_match("/(^\s*|\s*;\s*)text-align:\s*(center|left|right|justify)\s*($|;)/sxu", $style, $regs)) { $this->settings["alignment"]["horizontal"] = $regs[2]; }
			// vertical-align: top, bottom, center
			if (preg_match("/(^\s*|\s*;\s*)vertical-align:\s*(top|bottom|center)\s*($|;)/sxu", $style, $regs)) { $this->settings["alignment"]["vertical"] = $regs[2]; }
			// locked (нельзя будет менять значение если лист защищён)
			if (preg_match("/(^\s*|\s*;\s*)locked\s*($|;)/sxu", $style, $regs)) { $this->settings["protection"]["locked"] = true; }
			// hidden (нельзя будет видеть значение если лист защищён)
			if (preg_match("/(^\s*|\s*;\s*)hidden\s*($|;)/sxu", $style, $regs)) { $this->settings["protection"]["hidden"] = true; }

			// format: code(...) - код для форматирования по Excel
			if (preg_match("/(^\s*|\s*;\s*)format:\s*code\(([^;]+?)\)\s*($|;)/sxu", $style, $regs)) { $this->settings["numberformat"]["code"] = $regs[2]; }
		}
		else { $this->settings = $style; }
	}

	function settings() { return $this->settings; }
}