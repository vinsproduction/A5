function $(element)
{
	if (arguments.length > 1)
	{
		for (var i = 0, elements = [], length = arguments.length; i < length; i++)
		elements.push($(arguments[i]));
		return elements;
	}
	if (typeof element == 'string') { element = document.getElementById(element); }
	return element;
}

function is_empty_object(obj)
{
	var empty = true;
	for (i in obj) { empty = false; break; }
	return empty;
}

function get_object_length(obj)
{
	var count = 0;
	for (i in obj) { count++; }
	return count;
}

window.simple_button_pressed = null;
function simple_button_press(callback, obj)
{
	if (simple_button_pressed != null) { simple_button_pressed.className = 'simple_button_off'; }
	obj.className = 'simple_button_on';
	callback();
	simple_button_pressed = obj;
}

function simple_button_over(obj)
{
	if (obj.className != 'simple_button_on')
	{ obj.className = 'simple_button_over'; }
}

function simple_button_out(obj)
{
	if (obj.className != 'simple_button_on')
	{ obj.className = 'simple_button_off'; }
}

function tb_button_over(obj) { if (obj.className != 'tb_button_disabled') obj.className = 'tb_button_on'; }
function tb_button_out(obj) { if (obj.className != 'tb_button_disabled') obj.className = 'tb_button_off'; }

function add_event_listener(o, evt, func)
{
	if (o.attachEvent) { o.attachEvent('on' + evt, func); }
	else { o.addEventListener(evt, func, false); }
}

function remove_event_listener(o, evt, func)
{
	if (o.detachEvent) { o.detachEvent('on' + evt, func); }
	else { o.removeEventListener(evt, func, false); }
}

function disable_selection(elem)
{
	if (!elem) { elem = document.body; }
	elem.style.MozUserSelect = 'none';
	elem.style.KhtmlUserSelect = 'none';
	elem.style.WebkitUserSelect = 'none';
	elem.unselectable = 'on';
	var childs = elem.childNodes;
	if (childs)
	{
		for (var i = 0, c = childs.length; i < c; i++)
		{
			try
			{
				childs[i].style.MozUserSelect = 'none';
				childs[i].style.KhtmlUserSelect = 'none';
				childs[i].style.WebkitUserSelect = 'none';
				childs[i].unselectable = 'on';
				disable_selection(childs[i]);
			} catch(e) {}
		}
	}
}

function enable_selection(elem)
{
	if (!elem) { elem = document.body; }
	elem.style.MozUserSelect = 'text';
	elem.style.KhtmlUserSelect = 'auto';
	elem.style.WebkitUserSelect = 'auto';
	elem.unselectable = 'off';
	var childs = elem.childNodes;
	if (childs)
	{
		for (var i = 0, c = childs.length; i < c; i++)
		{
			try
			{
				childs[i].style.MozUserSelect = 'text';
				childs[i].style.KhtmlUserSelect = 'auto';
				childs[i].style.WebkitUserSelect = 'auto';
				childs[i].unselectable = 'off';
				enable_selection(childs[i]);
			} catch(e) {}
		}
	}
}

function append_url_param(url, name, value)
{
	var question = ''; var ampersand = '';
	var question_index = url.indexOf('?');
	if (question_index == -1) { question = '?'; }
	else if (url.substr(question_index + 1).length) { ampersand = '&'; } 
	return url + question + ampersand + encodeURIComponent(name) + '=' + encodeURIComponent(value);
}