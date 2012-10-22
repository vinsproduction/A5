CREATE OR REPLACE FUNCTION cms_get_current_language() RETURNS character
AS $$
    if (!defined($_SHARED{'cms_current_language'}))
    { $_SHARED{'cms_current_language'} = spi_exec_query('SELECT lang FROM cms_set_current_language(null) cl(lang)')->{'rows'}[0]->{'lang'}; }
    return $_SHARED{'cms_current_language'};
$$
LANGUAGE plperl;

CREATE OR REPLACE FUNCTION cms_get_view_mode() RETURNS smallint
AS $$
    if (!defined($_SHARED{'cms_current_view_mode'}))
    { $_SHARED{'cms_current_view_mode'} = spi_exec_query('SELECT view_mode FROM cms_set_view_mode(null) vm(view_mode)')->{'rows'}[0]->{'view_mode'}; }
    return $_SHARED{'cms_current_view_mode'};
$$
LANGUAGE plperl;

CREATE OR REPLACE FUNCTION cms_set_current_language(lang_name character) RETURNS character
AS $$
	my $lang_name = shift;
	undef($_SHARED{'cms_current_language'});
	
	if (defined($lang_name)) { $_SHARED{'cms_current_language'} = $lang_name; }
	else
	{
	    my $rv = spi_exec_query('SELECT lang FROM cms_languages WHERE is_default = 1 LIMIT 1');
	    if ($rv->{'processed'} > 0) { $_SHARED{'cms_current_language'} = $rv->{'rows'}[0]->{'lang'}; }
	}
	
	return $_SHARED{'cms_current_language'};
$$
LANGUAGE plperl;

CREATE OR REPLACE FUNCTION cms_set_view_mode(view_mode integer) RETURNS integer
AS $$
    my $view_mode = shift;
    
    if (!defined($view_mode)) { $_SHARED{'cms_current_view_mode'} = 0; }
    else { $_SHARED{'cms_current_view_mode'} = $view_mode ? 1 : 0; }

    return $_SHARED{'cms_current_view_mode'};
$$
LANGUAGE plperl;