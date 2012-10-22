function set_cookie(name, value, expires, path, domain, secure)
{
	var curCookie = name + "=" + escape(value) +
	((expires) ? "; expires=" +	expires.toGMTString() : "") +
	((path) ? "; path=" + path : "") +
	((domain) ? "; domain=" + domain : "") +
	((secure) ? "; secure" : "")
	document.cookie = curCookie;
}
 
function get_cookie(name)
{
	var prefix = name + "=";
	var cookieStartIndex = document.cookie.indexOf(prefix);
	if (cookieStartIndex == -1) { return null; }
	var cookieEndIndex = document.cookie.indexOf(";", cookieStartIndex + prefix.length);
	if (cookieEndIndex == -1) { cookieEndIndex = document.cookie.length; }
	return unescape(document.cookie.substring(cookieStartIndex + prefix.length, cookieEndIndex));
}
 
// name - имя cookie
// [path] - путь, для которого cookie действительно
// [domain] - домен, для которого cookie действительно
function delete_cookie(name, path, domain)
{
	if (getCookie(name))
	{
		document.cookie = name + "=" +
		((path) ? "; path=" + path : "") +
		((domain) ? "; domain=" + domain : "") +
		"; expires=Thu, 01-Jan-70 00:00:01 GMT"
	} 
}