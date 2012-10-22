var NTCal = new Object();

NTCal.frmobj = null;
NTCal.frmwnd = null;
NTCal.setobj = null;
NTCal.latestobjname = null;
NTCal.timer = null;
NTCal.names = new Array();
NTCal.names.month = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
NTCal.names.weekday = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
NTCal.names.today= 'сегодня';
NTCal.currDate = null;

NTCal.setHTML = function(month, year)
{
	NTCal.frmwnd.document.body.innerHTML = NTCal.getHTML(month, year);
	NTCal.reset();
}

NTCal.validDate = function (s) { return NTCal.str2date(s) ? true : false; }

NTCal.str2date = function (s)
{
	if (s)
	{
		var RegYMDHIS = /^(\d{4})[-|.|\/](\d+)[-|.|\/](\d+)\s+(\d+):(\d+):(\d+)$/i; // yyyy-mm-dd hh:ii:ss
		var RegDMYHIS = /^(\d+)[-|.|\/](\d+)[-|.|\/](\d{4})\s+(\d+):(\d+):(\d+)$/i; // dd-mm-yyyy hh:ii:ss
		var RegYMD = /^(\d{4})[-|.|\/](\d+)[-|.|\/](\d+)$/i; // yyyy-mm-dd
		var RegDMY = /^(\d+)[-|.|\/](\d+)[-|.|\/](\d{4})$/i; // dd-mm-yyyy
		var RegDMY2 = /^(\d+)[-|.|\/](\d+)[-|.|\/](\d{2})$/i; // dd-mm-yy

		var date = RegYMDHIS.exec(s);
		if (date) { return (new Date(date[1],date[2]-1,date[3],date[4],date[5],date[6])); }

		var date = RegDMYHIS.exec(s);
		if (date) { return (new Date(date[3],date[2]-1,date[1],date[4],date[5],date[6])); }

		var date = RegYMD.exec(s);
		if (date) { return (new Date(date[1],date[2]-1,date[3])); }

		var date = RegDMY.exec(s);
		if (date) { return (new Date(date[3],date[2]-1,date[1])); }

		var date = RegDMY2.exec(s);
		if (date)
		{
			var Year = Number(date[3]);
			if (Year < 10) { Year += 2000; } else { Year += 1900; }
			return (new Date(Year, (date[2]-1), date[1]));
		}
	}
	return null;
}

NTCal.getHTML = function(month, year, currDate)
{
	var drawMonth = new Date();

	drawMonth.setYear(year);
	drawMonth.setMonth(month, 1);

	var currDate = currDate ? currDate : NTCal.currDate;
	NTCal.currDate = currDate;
	
	var thisMonth = drawMonth.getMonth();
	var nextMonth = (thisMonth == 11) ? 0 : thisMonth + 1;
	var prevMonth = (thisMonth == 0) ? 11 : thisMonth - 1;
	
	var thisYear = drawMonth.getFullYear();
	var nextYear = thisYear + 1;
	var prevYear = thisYear - 1;

	var html0 = '';
	html0 += '<table>'
	html0 += '<tr>';
	html0 += '<td>';
	html0 += '<select onchange="parent.NTCal.setHTML(this.options[this.selectedIndex].value, ' + thisYear + ')">';
	for (i = 0, c = NTCal.names.month.length; i < c; i++) { html0 += '<option value="' + i + '"' + (thisMonth == i ? ' selected' : '') + '>' + NTCal.names.month[i] + '</option>'; }
	html0 += '</select>';
	html0 += '</td>';
	html0 += '<td>&nbsp;</td>';
	html0 += '<td><input type="button" class="ntcalendar_nextback" value="&laquo;" onclick="parent.NTCal.setHTML(' + thisMonth + ', ' + prevYear + ');"></td>';
	html0 += '<td class="ntcalendar_currentmonth"><input class="ntcalendar_nextback" style="width: 35px;" type="text" onChange="if (!isNaN(parseInt(this.value))) { parent.NTCal.setHTML(' + thisMonth + ', parseInt(this.value)); }" value="' + thisYear + '"></td>';
	html0 += '<td><input type="button" class="ntcalendar_nextback" value="&raquo;" onclick="parent.NTCal.setHTML(' + thisMonth + ', ' + nextYear + ');"></td>';
	html0 += '</tr>';
	html0 += '</table>';

	var html1 = '';
	html1 += '<table style="width: 100%">';
	html1 += '<tr>';
	for (var i = 0; i < NTCal.names.weekday.length; i++)
	{ html1 += '<td class="' + (i < NTCal.names.weekday.length - 2 ? 'ntcalendar_weekday' : 'ntcalendar_holiday') + '" style="text-align: center;">' + NTCal.names.weekday[i] + '</td>'; }
	html1 += '</tr>';

	html1 += '<tr>';
	var daysToStart = (drawMonth.getDay() == 0) ? 7 : drawMonth.getDay();
	for (var i = 0; i < daysToStart - 1; i++) { html1 += '<td></td>'; }

	for (var i = 1; i < 33; i++)
	{
		drawMonth.setDate(i);
		if (drawMonth.getDay() == 1) { html1 += '</tr><tr>'; }

		if (drawMonth.getMonth() == thisMonth)
		{
			var currentDay = (drawMonth.getFullYear() == currDate.getFullYear() && drawMonth.getMonth() == currDate.getMonth() && i == currDate.getDate());
			var dayClass = (drawMonth.getDay() == 0 || drawMonth.getDay() == 6) ? 'ntcalendar_day_holiday' : 'ntcalendar_day_weekday';
			var formatedDate = '';
			formatedDate += (i < 10 ? '0' + i + '.' : i + '.');
			formatedDate += ((drawMonth.getMonth()+1) < 10 ? '0' + (drawMonth.getMonth()+1) + '.' : (drawMonth.getMonth()+1) + '.');
			formatedDate += drawMonth.getFullYear();
			html1 += '<td' + (currentDay ? ' style="border: 1px #000000 solid"' : '') + ' class="ntcalendar_day_out" onmouseover="this.className = \'ntcalendar_day_over\';" onmouseout="this.className = \'ntcalendar_day_out\';" onclick="parent.NTCal.setdate(\'' + formatedDate + '\'); return false;" align="center"><span class="' + dayClass + '">' + i + '</span></td>';
		}
		else { break; }
	}

	if (drawMonth.getDay() != 1)
	{
		var daysToEnd = 8 - ((drawMonth.getDay() == 0) ? 7 : drawMonth.getDay());
		for (var i = 0; i < daysToEnd; i++) { html1 += '<td></td>'; }
	}

	html1 += '</tr>';
	html1 += '</table>';

	currDate = new Date();
	
	var formatedDate = '';
	formatedDate += (currDate.getDate() < 10 ? '0' + currDate.getDate() + '.' : currDate.getDate() + '.');
	formatedDate += ((currDate.getMonth()+1) < 10 ? '0' + (currDate.getMonth()+1) + '.' : (currDate.getMonth()+1) + '.');
	formatedDate += currDate.getFullYear();

	html1 += '<table style="width: 100%">';
	html1 += '<tr>';
	html1 += '<td style="text-align: center;" class="ntcalendar_day_out" onmouseover="this.className = \'ntcalendar_day_over\';" onmouseout="this.className = \'ntcalendar_day_out\';" onclick="parent.NTCal.setdate(\'01.01.' + thisYear + '\'); return false;"><div style="padding: 3px;">01.01.' + thisYear + '</div></td>';
	html1 += '<td style="text-align: center;" class="ntcalendar_day_out" onmouseover="this.className = \'ntcalendar_day_over\';" onmouseout="this.className = \'ntcalendar_day_out\';" onclick="parent.NTCal.setdate(\'' + formatedDate + '\'); return false;"><div style="padding: 3px;"><b>' + NTCal.names.today + '</b></div></td>';
	html1 += '<td style="text-align: center;" class="ntcalendar_day_out" onmouseover="this.className = \'ntcalendar_day_over\';" onmouseout="this.className = \'ntcalendar_day_out\';" onclick="parent.NTCal.setdate(\'31.12.' + thisYear + '\'); return false;"><div style="padding: 3px;">31.12.' + thisYear + '</div></td>';
	html1 += '</tr>';
	html1 += '</table>';
	
	var html = '';
	html += '<table id="cal_table"><tr><td style="border: 1px #000000 solid; padding: 2px;">';
	html += html0;
	html += '<div id="cal_layout">';
	html += html1;
	html += '</div>';
	html += '</td></tr></table>';
	
	return html;
}

NTCal.getObjLeft = function (obj)
{
	var left = obj.offsetLeft;
	if (obj.offsetParent) { left += NTCal.getObjLeft(obj.offsetParent) };
	return left;
}

NTCal.getObjTop = function (obj)
{
	var top = obj.offsetTop;
	if (obj.offsetParent) { top += NTCal.getObjTop(obj.offsetParent); }
	return top;
}

NTCal.setdate = function(d)
{
	NTCal.setobj.value = d;
	NTCal.hide();
}

NTCal.reset = function()
{
	NTCal.frmobj.style.width = NTCal.frmobj.style.height = '10px';
	NTCal.frmobj.style.width = NTCal.frmwnd.document.getElementById('cal_table').offsetWidth + 'px';
	NTCal.frmobj.style.height = NTCal.frmwnd.document.getElementById('cal_table').offsetHeight + 'px';
}

NTCal.show = function (obj, evt)
{
	if (!NTCal.frmobj)
	{
		NTCal.frmobj = document.createElement('IFRAME');
		NTCal.frmobj.src = 'javascript:void(0);';
		NTCal.frmobj.allowTransparency = false;
		NTCal.frmobj.frameBorder = '0';
		NTCal.frmobj.scrolling = 'no';
		NTCal.frmobj.style.position = 'absolute';
		NTCal.frmobj.style.display = 'none';
		NTCal.frmobj.style.left = '0px';
		NTCal.frmobj.style.top = '0px';
		NTCal.frmobj.style.width = NTCal.frmobj.style.height = '10px';
		document.body.appendChild(NTCal.frmobj);
		NTCal.frmwnd = NTCal.frmobj.contentWindow;
		
		var html = '';
		html += '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
		html += '<html>';
		html += '<head>';
		html += '<title></title>';
		html += '<style type="text/css">';
		html += 'html, body { width: 100%; height: 100%; padding: 0px; margin: 0px; background-color: #ffffff; }';
		html += 'html, body, table, td, form, select, input { font-family: Tahoma; font-size: 11px; }';
		html += 'table { border-collapse: collapse; border: none; }';
		html += 'table td { padding: 0px; }';
		html += '.ntcalendar_nextback { width: 20px; color: #333333; font-weight: bold; }';
		html += '.ntcalendar_weekday { background-color: #F5E5FF; height: 20px; }';
		html += '.ntcalendar_holiday { background-color: #FFE5F5; height: 20px; }';
		html += '.ntcalendar_day_out { cursor: pointer; background-color: transparent; padding: 4px; }';
		html += '.ntcalendar_day_over { cursor: pointer; background-color: #D5D5D5; padding: 4px; }';
		html += '.ntcalendar_day_holiday { cursor: pointer; color: #FF0000; }';
		html += '.ntcalendar_day_weekday { cursor: pointer; color: #000000; }';
		html += '</style>';
		html += '</head>';
		html += '<body>';
		html += '</body>';
		html += '</html>';
		
		NTCal.frmwnd.document.open();
		NTCal.frmwnd.document.write(html);
		NTCal.frmwnd.document.close();
		
		if (document.addEventListener)
		{
			document.addEventListener('click', NTCal.hide, false);
			NTCal.frmwnd.document.body.addEventListener('click', NTCal.innerClick, false);
		}
		else
		{
			document.body.attachEvent('onclick', NTCal.hide);
			NTCal.frmwnd.document.body.attachEvent('onclick', NTCal.innerClick);
		}
	}

	if (obj.name == NTCal.latestobjname) { NTCal.hide(); }
	else
	{
		NTCal.setobj = obj;
		NTCal.hide();
		NTCal.frmobj.style.top = NTCal.getObjTop(obj) + obj.offsetHeight + 'px';
		NTCal.frmobj.style.left = NTCal.getObjLeft(obj) + 'px';

		var currDate = new Date();
		if (NTCal.validDate(NTCal.setobj.value)) { currDate = NTCal.str2date(NTCal.setobj.value); }

		NTCal.frmwnd.document.body.innerHTML = NTCal.getHTML(currDate.getMonth(), currDate.getFullYear(), currDate);
		NTCal.frmobj.style.display = 'block';
		NTCal.reset();
		NTCal.latestobjname = obj.name;
	}
	evt.cancelBubble = true;
}

NTCal.hide = function ()
{
	if (NTCal.frmobj)
	{
		NTCal.frmobj.style.display = 'none';
		NTCal.latestobjname = null;
	}
}

NTCal.innerClick = function (e)
{
	var evt = e ? e : window.event;
	evt.cancelBubble = true;
}