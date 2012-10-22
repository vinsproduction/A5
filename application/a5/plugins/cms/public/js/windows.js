function open_window(url, params)
{
	if (!params) { params = {}; }
	if (!params['width']) { params['width'] = '100'; }
	if (!params['height']) { params['height'] = '100'; }
	if (!params['location']) { params['location'] = 'no'; }
	if (!params['menubar']) { params['menubar'] = 'no'; }
	if (!params['resizable']) { params['resizable'] = 'yes'; }
	if (!params['scrollbars']) { params['scrollbars'] = 'no'; }
	if (!params['status']) { params['status'] = 'yes'; }
	if (!params['titlebar']) { params['titlebar'] = 'yes'; }
	if (!params['toolbar']) { params['toolbar'] = 'no'; }
	var params_str = '';
	for (i in params)
	{
		if (params_str.length) { params_str += ','; }
		params_str += i + '=' + params[i];	
	}
	return window.open(url, '_blank', params_str);
}

function standard_window(url)
{
	var params = {};
	params['width'] = Math.round(screen.availWidth * 0.8);
	params['height'] = Math.round(screen.availHeight * 0.8);
	params['location'] = 'yes';
	params['menubar'] = 'yes';
	params['resizable'] = 'yes';
	params['scrollbars'] = 'yes';
	params['status'] = 'yes';
	params['titlebar'] = 'yes';
	params['toolbar'] = 'yes';
	return open_window(url, params);
}

function browser_window(url)
{
	var params = {};
	params['width'] = Math.round(screen.availWidth * 0.8);
	params['height'] = Math.round(screen.availHeight * 0.8);
	return open_window(url, params);
}

function node_picker_window(url)
{
	var params = {};
	params['width'] = Math.round(screen.availWidth * 0.8);
	params['height'] = Math.round(screen.availHeight * 0.8);
	return open_window(url, params);
}